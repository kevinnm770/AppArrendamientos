<?php

namespace App\Services\Hacienda;

use App\Services\Hacienda\Contracts\XmlSignerInterface;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Firma XAdES-BES construida a mano con DOMDocument + openssl (sin librerías de
 * terceros), porque el ecosistema XAdES en PHP es escaso/poco mantenido.
 *
 * ESTADO: verificado internamente (firma RSA-SHA256 válida y digest de SignedProperties
 * consistente, comprobado con un certificado de prueba autofirmado — ver notas de la
 * sesión), pero PENDIENTE DE VALIDAR contra el sandbox real de Hacienda (Fase 1 del
 * plan) con un .p12 real. Si Hacienda rechaza por firma inválida, los puntos más
 * probables de ajuste son: el formato de X509IssuerName, o requisitos adicionales del
 * XSD v4.4 no visibles desde afuera del sandbox (p.ej. orden exacto de atributos).
 * Todo el resto del pipeline (auth, XML de negocio, envío) es independiente de esta
 * clase gracias a XmlSignerInterface.
 */
class XadesBesSigner implements XmlSignerInterface
{
    protected const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    protected const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    protected const ALG_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    protected const ALG_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    protected const ALG_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    protected const ALG_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    public function sign(string $xml, string $p12Path, string $pin): string
    {
        [$privateKeyPem, $certPem] = $this->readCertificate($p12Path, $pin);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($xml);

        $root = $doc->documentElement;

        $uid = str_replace('.', '', uniqid('', true));
        $signatureId = "xmldsig-{$uid}";
        $signedPropsId = "{$signatureId}-signedprops";
        $keyInfoId = "{$signatureId}-keyinfo";

        // 1) Digest del documento de negocio TAL COMO ESTÁ (sin el nodo Signature todavía,
        // lo que produce el mismo resultado que aplicar el transform enveloped-signature).
        // $root está adjunto al documento real (viene de loadXML), así que su C14N es fiable.
        $documentDigest = base64_encode(hash('sha256', $root->C14N(true, false), true));

        $certDerBase64 = $this->certificateDerBase64($certPem);
        $certDigest = base64_encode(hash('sha256', base64_decode($certDerBase64), true));
        [$issuerName, $serialNumber] = $this->issuerAndSerial($certPem);

        // 2) Construir TODO el árbol de la firma (con placeholders vacíos para los digests/firma
        // que aún no se pueden calcular) y adjuntarlo al documento ANTES de canonicalizar nada más.
        // IMPORTANTE: C14N sobre nodos recién creados con createElementNS pero aún DESADJUNTOS del
        // documento produce bytes distintos a los del mismo nodo una vez insertado (namespaces mal
        // resueltos). Por eso aquí se construye y se adjunta primero, y los digests se calculan
        // después sobre los nodos ya adjuntos, mutando solo su texto.
        $signatureNode = $doc->createElementNS(self::NS_DS, 'ds:Signature');
        $signatureNode->setAttribute('Id', $signatureId);

        [$signedInfoNode, $signedPropsDigestValueNode] = $this->buildSignedInfo($doc, $documentDigest, $signedPropsId);
        $signatureNode->appendChild($signedInfoNode);

        $signatureValueNode = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue', '');
        $signatureNode->appendChild($signatureValueNode);

        $signatureNode->appendChild($this->buildKeyInfo($doc, $keyInfoId, $certDerBase64));

        $signedPropertiesNode = $this->buildSignedProperties($doc, $signedPropsId, $certDigest, $issuerName, $serialNumber);
        $objectNode = $doc->createElementNS(self::NS_DS, 'ds:Object');
        $qualifyingProperties = $doc->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', "#{$signatureId}");
        $qualifyingProperties->appendChild($signedPropertiesNode);
        $objectNode->appendChild($qualifyingProperties);
        $signatureNode->appendChild($objectNode);

        $root->appendChild($signatureNode);

        // 3) Ahora que SignedProperties está adjunto en su posición final, calcular su digest real
        // y escribirlo en el placeholder ya insertado dentro de SignedInfo.
        $signedPropertiesDigest = base64_encode(hash('sha256', $signedPropertiesNode->C14N(true, false), true));
        $signedPropsDigestValueNode->nodeValue = $signedPropertiesDigest;

        // 4) Con SignedInfo ya adjunto y con AMBOS digests correctos, canonicalizar y firmar.
        $signatureValue = $this->signWithPrivateKey($signedInfoNode->C14N(true, false), $privateKeyPem);
        $signatureValueNode->nodeValue = $signatureValue;

        return $doc->saveXML();
    }

    protected function readCertificate(string $p12Path, string $pin): array
    {
        $p12Content = @file_get_contents($p12Path);

        if ($p12Content === false) {
            throw new RuntimeException("No se pudo leer el certificado .p12 en: {$p12Path}");
        }

        if (!openssl_pkcs12_read($p12Content, $certs, $pin)) {
            throw new RuntimeException('No se pudo abrir el certificado .p12: PIN incorrecto o archivo inválido.');
        }

        return [$certs['pkey'], $certs['cert']];
    }

    protected function certificateDerBase64(string $certPem): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $certPem)), function (string $line) {
            return $line !== '' && !str_starts_with($line, '-----');
        });

        return implode('', $lines);
    }

    protected function issuerAndSerial(string $certPem): array
    {
        $parsed = openssl_x509_parse($certPem);

        if ($parsed === false) {
            throw new RuntimeException('No se pudo leer el certificado X509 para extraer emisor/serie.');
        }

        $issuerParts = [];
        foreach ((array) ($parsed['issuer'] ?? []) as $key => $value) {
            $issuerParts[] = "{$key}={$value}";
        }

        $issuerName = implode(', ', $issuerParts);
        $serialNumber = (string) ($parsed['serialNumber'] ?? '0');

        return [$issuerName, $serialNumber];
    }

    protected function buildSignedProperties(DOMDocument $doc, string $signedPropsId, string $certDigest, string $issuerName, string $serialNumber): DOMElement
    {
        $signedProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', $signedPropsId);

        $signedSignatureProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');

        $signingTime = $doc->createElementNS(self::NS_XADES, 'xades:SigningTime', now('America/Costa_Rica')->format('Y-m-d\TH:i:sP'));
        $signedSignatureProperties->appendChild($signingTime);

        $signingCertificate = $doc->createElementNS(self::NS_XADES, 'xades:SigningCertificate');
        $cert = $doc->createElementNS(self::NS_XADES, 'xades:Cert');

        $certDigestNode = $doc->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::ALG_SHA256);
        $certDigestNode->appendChild($digestMethod);
        $certDigestNode->appendChild($doc->createElementNS(self::NS_DS, 'ds:DigestValue', $certDigest));
        $cert->appendChild($certDigestNode);

        $issuerSerial = $doc->createElementNS(self::NS_XADES, 'xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS(self::NS_DS, 'ds:X509IssuerName', htmlspecialchars($issuerName, ENT_XML1)));
        $issuerSerial->appendChild($doc->createElementNS(self::NS_DS, 'ds:X509SerialNumber', $serialNumber));
        $cert->appendChild($issuerSerial);

        $signingCertificate->appendChild($cert);
        $signedSignatureProperties->appendChild($signingCertificate);

        // Hacienda exige XAdES-EPES, no solo XAdES-BES: la firma DEBE declarar una política
        // de firma (rechazo real observado: "La firma del documento no tiene el Policy Id").
        // Usamos SignaturePolicyImplied (política implícita, sin URI/hash explícitos) porque
        // no tenemos verificado el hash SHA-256 exacto del documento de política que publica
        // Hacienda; si el sandbox vuelve a rechazar pidiendo la política explícita, hay que
        // cambiar esto por <xades:SignaturePolicyId> con el Identifier + SigPolicyHash reales.
        $signaturePolicyIdentifier = $doc->createElementNS(self::NS_XADES, 'xades:SignaturePolicyIdentifier');
        $signaturePolicyIdentifier->appendChild($doc->createElementNS(self::NS_XADES, 'xades:SignaturePolicyImplied'));
        $signedSignatureProperties->appendChild($signaturePolicyIdentifier);

        $signedProperties->appendChild($signedSignatureProperties);

        return $signedProperties;
    }

    /**
     * @return array{0: DOMElement, 1: DOMElement} [SignedInfo, nodo DigestValue de la referencia
     *   a SignedProperties (aún vacío — se rellena luego de adjuntar SignedProperties al árbol)]
     */
    protected function buildSignedInfo(DOMDocument $doc, string $documentDigest, string $signedPropsId): array
    {
        $signedInfo = $doc->createElementNS(self::NS_DS, 'ds:SignedInfo');

        $canonicalizationMethod = $doc->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::ALG_C14N);
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $doc->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::ALG_RSA_SHA256);
        $signedInfo->appendChild($signatureMethod);

        // Referencia 1: el documento de negocio completo. URI="" (documento completo) en vez de
        // un fragmento "#Id" para no tener que inyectar un atributo Id no declarado en el XSD
        // oficial en el elemento raíz del comprobante (digest ya conocido, calculado antes de
        // construir la firma).
        $documentReference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $documentReference->setAttribute('URI', '');
        $transforms = $doc->createElementNS(self::NS_DS, 'ds:Transforms');
        $transforms->appendChild($this->transform($doc, self::ALG_ENVELOPED));
        $transforms->appendChild($this->transform($doc, self::ALG_C14N));
        $documentReference->appendChild($transforms);
        $this->appendDigest($doc, $documentReference, $documentDigest);
        $signedInfo->appendChild($documentReference);

        // Referencia 2: las XAdES SignedProperties (obligatoria para XAdES-BES). Su digest todavía
        // no existe en este punto (SignedProperties ni siquiera está construido) — se deja vacío
        // y el llamador lo completa una vez que SignedProperties está adjunto al árbol final.
        $signedPropsReference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $signedPropsReference->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $signedPropsReference->setAttribute('URI', "#{$signedPropsId}");
        $spTransforms = $doc->createElementNS(self::NS_DS, 'ds:Transforms');
        $spTransforms->appendChild($this->transform($doc, self::ALG_C14N));
        $signedPropsReference->appendChild($spTransforms);
        $signedPropsDigestValueNode = $this->appendDigest($doc, $signedPropsReference, '');
        $signedInfo->appendChild($signedPropsReference);

        return [$signedInfo, $signedPropsDigestValueNode];
    }

    protected function transform(DOMDocument $doc, string $algorithm): DOMElement
    {
        $transform = $doc->createElementNS(self::NS_DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', $algorithm);

        return $transform;
    }

    protected function appendDigest(DOMDocument $doc, DOMElement $reference, string $digestValue): DOMElement
    {
        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::ALG_SHA256);
        $reference->appendChild($digestMethod);

        $digestValueNode = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $digestValue);
        $reference->appendChild($digestValueNode);

        return $digestValueNode;
    }

    protected function signWithPrivateKey(string $canonicalizedSignedInfo, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('No se pudo cargar la llave privada del certificado .p12.');
        }

        if (!openssl_sign($canonicalizedSignedInfo, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falló la firma RSA-SHA256 del SignedInfo.');
        }

        return base64_encode($signature);
    }

    protected function buildKeyInfo(DOMDocument $doc, string $keyInfoId, string $certDerBase64): DOMElement
    {
        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        $x509Data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        $x509Data->appendChild($doc->createElementNS(self::NS_DS, 'ds:X509Certificate', $certDerBase64));
        $keyInfo->appendChild($x509Data);

        return $keyInfo;
    }
}
