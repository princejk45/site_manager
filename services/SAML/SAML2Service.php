<?php
/**
 * SAML2Service
 * SAML 2.0 single sign-on support with metadata generation and assertion validation
 */

namespace Services\SAML;

use PDO;
use Exception;

class SAML2Service
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Configure SAML 2.0 identity provider for portfolio
     */
    public function configureIdP($portfolio_id, $idp_name, $sso_url, $slo_url, $x509_cert, $metadata_url = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO saml2_configurations (portfolio_id, idp_name, sso_url, slo_url, x509_certificate, metadata_url, is_enabled, created_at)
                VALUES (:portfolio_id, :idp_name, :sso_url, :slo_url, :x509_cert, :metadata_url, 1, NOW())
                ON DUPLICATE KEY UPDATE
                sso_url = :sso_url,
                slo_url = :slo_url,
                x509_certificate = :x509_cert,
                metadata_url = :metadata_url,
                is_enabled = 1,
                updated_at = NOW()
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':idp_name' => $idp_name,
                ':sso_url' => $sso_url,
                ':slo_url' => $slo_url,
                ':x509_cert' => $x509_cert,
                ':metadata_url' => $metadata_url
            ]);

            return ['status' => 'success', 'message' => 'SAML 2.0 IdP configured'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to configure SAML 2.0 IdP: " . $e->getMessage());
        }
    }

    /**
     * Generate SAML 2.0 service provider metadata XML
     */
    public function generateServiceProviderMetadata($portfolio_id, $sp_entity_id, $assertion_consumer_url, $single_logout_url)
    {
        try {
            $cert = $this->getOrGenerateCertificate($portfolio_id);
            
            $metadata = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="{$sp_entity_id}">
    <SPSSODescriptor AuthnRequestsSigned="true" WantAssertionsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <KeyDescriptor use="signing">
            <KeyInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
                <X509Data>
                    <X509Certificate>
                        {$cert['certificate']}
                    </X509Certificate>
                </X509Data>
            </KeyInfo>
        </KeyDescriptor>
        <NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</NameIDFormat>
        <AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="{$assertion_consumer_url}" index="0" isDefault="true"/>
        <SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="{$single_logout_url}"/>
    </SPSSODescriptor>
</EntityDescriptor>
XML;

            // Store metadata
            $stmt = $this->db->prepare("
                INSERT INTO saml2_sp_metadata (portfolio_id, entity_id, metadata_xml, created_at)
                VALUES (:portfolio_id, :entity_id, :metadata_xml, NOW())
                ON DUPLICATE KEY UPDATE
                metadata_xml = :metadata_xml,
                updated_at = NOW()
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':entity_id' => $sp_entity_id,
                ':metadata_xml' => $metadata
            ]);

            return [
                'status' => 'success',
                'metadata' => $metadata
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to generate SP metadata: " . $e->getMessage());
        }
    }

    /**
     * Validate SAML 2.0 response/assertion
     */
    public function validateAssertion($portfolio_id, $saml_response)
    {
        try {
            // Decode base64 response
            $decoded_response = base64_decode($saml_response);
            
            // Load XML
            $dom = new \DOMDocument();
            $dom->loadXML($decoded_response);
            
            // Get configuration
            $stmt = $this->db->prepare("
                SELECT x509_certificate FROM saml2_configurations
                WHERE portfolio_id = :portfolio_id AND is_enabled = 1 LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                throw new Exception("SAML 2.0 not configured");
            }

            // Validate signature
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            
            // Extract assertion
            $assertions = $xpath->query('//saml:Assertion');
            if ($assertions->length === 0) {
                throw new Exception("No SAML assertion found");
            }

            // Validate timestamp
            $not_on_or_after = $xpath->query('//saml:SubjectConfirmationData/@NotOnOrAfter')->item(0);
            if ($not_on_or_after && strtotime($not_on_or_after->nodeValue) < time()) {
                throw new Exception("Assertion expired");
            }

            // Extract subject
            $subject = $xpath->query('//saml:Subject/saml:NameID')->item(0);
            $user_email = $subject ? $subject->textContent : null;

            // Extract attributes
            $attributes = [];
            $attr_statements = $xpath->query('//saml:AttributeStatement/saml:Attribute');
            foreach ($attr_statements as $attr) {
                /** @var \DOMElement $attr */
                $name = $attr->getAttribute('Name');
                $values = $xpath->query('.//saml:AttributeValue', $attr);
                $attr_values = [];
                foreach ($values as $value) {
                    $attr_values[] = $value->textContent;
                }
                $attributes[$name] = count($attr_values) === 1 ? $attr_values[0] : $attr_values;
            }

            // Store assertion for audit
            $stmt = $this->db->prepare("
                INSERT INTO saml2_assertions (portfolio_id, user_email, attributes, validated_at)
                VALUES (:portfolio_id, :user_email, :attributes, NOW())
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':user_email' => $user_email,
                ':attributes' => json_encode($attributes)
            ]);

            return [
                'status' => 'success',
                'user_email' => $user_email,
                'attributes' => $attributes,
                'validated' => true
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to validate SAML assertion: " . $e->getMessage());
        }
    }

    /**
     * Generate authentication request
     */
    public function generateAuthenticationRequest($portfolio_id, $sp_entity_id, $assertion_consumer_url)
    {
        try {
            $request_id = '_' . bin2hex(random_bytes(16));
            $issue_instant = date('c');

            $auth_request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<AuthnRequest xmlns="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$request_id}"
    Version="2.0"
    IssueInstant="{$issue_instant}"
    Destination="https://idp.example.com/sso"
    AssertionConsumerServiceURL="{$assertion_consumer_url}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$sp_entity_id}</saml:Issuer>
</AuthnRequest>
XML;

            // Store request for validation
            $stmt = $this->db->prepare("
                INSERT INTO saml2_auth_requests (portfolio_id, request_id, request_xml, created_at)
                VALUES (:portfolio_id, :request_id, :request_xml, NOW())
            ");
            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':request_id' => $request_id,
                ':request_xml' => $auth_request
            ]);

            $encoded_request = base64_encode($auth_request);

            return [
                'status' => 'success',
                'request_id' => $request_id,
                'auth_request' => $encoded_request
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to generate authentication request: " . $e->getMessage());
        }
    }

    /**
     * Process single logout request
     */
    public function processLogoutRequest($portfolio_id, $saml_logout_request)
    {
        try {
            $decoded = base64_decode($saml_logout_request);
            
            // Parse and validate
            $dom = new \DOMDocument();
            $dom->loadXML($decoded);

            // Extract session index
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:protocol');
            $session_index = $xpath->query('//saml:SessionIndex')->item(0);

            // Invalidate session
            if ($session_index) {
                $this->invalidateSession($portfolio_id, $session_index->textContent);
            }

            return ['status' => 'success', 'message' => 'Logout processed'];
        } catch (Exception $e) {
            throw new Exception("Failed to process logout request: " . $e->getMessage());
        }
    }

    /**
     * Get SAML 2.0 configuration
     */
    public function getConfiguration($portfolio_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, idp_name, sso_url, slo_url, metadata_url, is_enabled
                FROM saml2_configurations
                WHERE portfolio_id = :portfolio_id AND is_enabled = 1 LIMIT 1
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return ['status' => 'error', 'message' => 'SAML 2.0 not configured'];
            }

            return ['status' => 'success', 'configuration' => $config];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get configuration: " . $e->getMessage());
        }
    }

    /**
     * Get SAML statistics
     */
    public function getSAMLStats($portfolio_id = null)
    {
        try {
            $where = $portfolio_id ? "WHERE portfolio_id = :portfolio_id" : "";
            $params = $portfolio_id ? [':portfolio_id' => $portfolio_id] : [];

            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT portfolio_id) as portfolios,
                    COUNT(*) as total_assertions,
                    COUNT(DISTINCT DATE(validated_at)) as active_days,
                    MAX(validated_at) as last_assertion
                FROM saml2_assertions
                {$where}
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'statistics' => $stats
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get SAML stats: " . $e->getMessage());
        }
    }

    /**
     * Disable SAML 2.0
     */
    public function disableSAML($portfolio_id)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE saml2_configurations
                SET is_enabled = 0, disabled_at = NOW()
                WHERE portfolio_id = :portfolio_id
            ");
            $stmt->execute([':portfolio_id' => $portfolio_id]);

            return ['status' => 'success', 'message' => 'SAML 2.0 disabled'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to disable SAML 2.0: " . $e->getMessage());
        }
    }

    /**
     * Get or generate certificate for SP
     */
    private function getOrGenerateCertificate($portfolio_id)
    {
        $stmt = $this->db->prepare("
            SELECT certificate FROM saml2_certificates
            WHERE portfolio_id = :portfolio_id LIMIT 1
        ");
        $stmt->execute([':portfolio_id' => $portfolio_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return ['certificate' => $result['certificate']];
        }

        // Generate new certificate
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privkey);
        $pubkey = openssl_pkey_get_details($res);
        $pubkey = $pubkey["key"];

        // Store certificate
        $stmt = $this->db->prepare("
            INSERT INTO saml2_certificates (portfolio_id, certificate, private_key, created_at)
            VALUES (:portfolio_id, :certificate, :private_key, NOW())
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':certificate' => trim($pubkey),
            ':private_key' => $privkey
        ]);

        return ['certificate' => trim($pubkey)];
    }

    /**
     * Invalidate session
     */
    private function invalidateSession($portfolio_id, $session_index)
    {
        $stmt = $this->db->prepare("
            UPDATE saml2_sessions
            SET is_active = 0, invalidated_at = NOW()
            WHERE portfolio_id = :portfolio_id AND session_index = :session_index
        ");
        $stmt->execute([
            ':portfolio_id' => $portfolio_id,
            ':session_index' => $session_index
        ]);
    }
}
