<?php
/**
 * Carolina Made PromoStandards SOAP Client
 * Calls Carolina Made's endpoints directly — no third-party API required.
 */
class CarolinaMadeAPI {
    const BASE_URL   = 'https://promostandards.carolinamade.com/cgi-bin/ws/';
    const WS_VERSION = '1.0.0';

    private $username;
    private $password;

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    private function client($service) {
        $wsdlUrl = self::BASE_URL . $service . '?wsdl';
        $context = stream_context_create([
            'ssl'  => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
            'http' => [
                'timeout'    => 30,
                'user_agent' => 'PHP-SOAP/PromoStandards',
            ],
        ]);

        // libxml uses its own HTTP layer for WSDL loading — must set this separately
        libxml_set_streams_context($context);

        // Fetch and cache the WSDL locally via curl so libxml never needs to hit the wire
        $cacheDir  = sys_get_temp_dir();
        $cacheFile = $cacheDir . '/cm_wsdl_' . md5($wsdlUrl) . '.xml';
        if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > 86400) {
            // Try ?wsdl then ?WSDL (some PromoStandards servers are case-sensitive)
            $wsdlBody = null;
            foreach ([$wsdlUrl, str_replace('?wsdl', '?WSDL', $wsdlUrl)] as $tryUrl) {
                $ch = curl_init($tryUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 PHP-SOAP/PromoStandards',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER     => ['Accept: text/xml,application/xml,application/xhtml+xml,*/*'],
                ]);
                $body    = curl_exec($ch);
                $curlErr = curl_error($ch);
                curl_close($ch);
                // Valid WSDL must contain XML definitions, not HTML
                if ($body && (strpos($body, 'definitions') !== false || strpos($body, 'wsdl:') !== false)) {
                    $wsdlBody = $body;
                    break;
                }
            }
            if ($wsdlBody) {
                file_put_contents($cacheFile, $wsdlBody);
            } elseif (!file_exists($cacheFile)) {
                throw new RuntimeException("WSDL fetch for {$service} returned HTML or empty. Run wsdl_debug to inspect the raw response. Last curl error: {$curlErr}");
            }
        }

        return new SoapClient($cacheFile, [
            'exceptions'     => true,
            'trace'          => false,
            'cache_wsdl'     => WSDL_CACHE_NONE,
            'stream_context' => $context,
            'location'       => self::BASE_URL . $service,
        ]);
    }

    private function auth() {
        return [
            'wsVersion' => self::WS_VERSION,
            'id'        => $this->username,
            'password'  => $this->password,
        ];
    }

    public function testConnection() {
        try {
            $res   = $this->client('productData.php')->GetProductCloseOut($this->auth());
            $items = $res->ProductCloseOutArray->ProductCloseOut ?? null;
            $count = 0;
            if ($items) {
                $count = is_array($items) ? count($items) : 1;
            }
            return ['ok' => true, 'product_count' => $count];
        } catch (SoapFault $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function getProductIds() {
        $res   = $this->client('productData.php')->GetProductCloseOut($this->auth());
        $items = $res->ProductCloseOutArray->ProductCloseOut ?? null;
        if (!$items) return [];
        if (!is_array($items)) $items = [$items];
        return array_map(fn($i) => (string)($i->productId ?? ''), $items);
    }

    public function getProduct($productId) {
        return $this->client('productData.php')->GetProduct(array_merge($this->auth(), [
            'localizationCountry'  => 'US',
            'localizationLanguage' => 'en',
            'productId'            => $productId,
            'partId'               => '',
            'colorName'            => '',
            'ApparelSizeArray'     => [],
        ]));
    }

    public function getPricing($productId) {
        try {
            return $this->client('pricing.php')->GetConfigurationAndPricing(array_merge($this->auth(), [
                'localizationCountry'  => 'US',
                'localizationLanguage' => 'en',
                'currency'             => 'USD',
                'fobId'                => '1',
                'productId'            => $productId,
                'partId'               => '',
                'configurationType'    => 'Blank',
            ]));
        } catch (Exception $e) { return null; }
    }

    public function getInventory($productId) {
        try {
            return $this->client('inventory.php')->GetInventoryLevels(array_merge($this->auth(), [
                'productId' => $productId,
                'Filter'    => ['SellableArray' => ['Sellable' => true]],
            ]));
        } catch (Exception $e) { return null; }
    }

    public function getMediaContent($productId) {
        try {
            return $this->client('mediaContent.php')->GetMediaContent(array_merge($this->auth(), [
                'productId'      => $productId,
                'partId'         => '',
                'mediaType'      => 'Image',
                'ClassTypeArray' => [],
            ]));
        } catch (Exception $e) { return null; }
    }

    // ── Parse a GetProduct response into a flat array ─────────────────────
    public static function parseProduct($response, $productId) {
        if (!$response || !isset($response->Product)) return null;
        $prod = $response->Product;

        $name = (string)($prod->productName ?? $productId);
        $desc = (string)($prod->description ?? '');

        // Colors & sizes from parts
        $colors = []; $sizes = []; $price = ''; $image = '';
        if (isset($prod->ProductPartArray->ProductPart)) {
            $parts = $prod->ProductPartArray->ProductPart;
            if (!is_array($parts)) $parts = [$parts];

            foreach ($parts as $part) {
                // Color
                if (isset($part->ColorArray->Color)) {
                    $cs = $part->ColorArray->Color;
                    if (!is_array($cs)) $cs = [$cs];
                    foreach ($cs as $c) {
                        $cn = (string)($c->colorName ?? '');
                        if ($cn && !in_array($cn, $colors)) $colors[] = $cn;
                    }
                }
                // Size
                if (isset($part->ApparelSize->labelSize)) {
                    $sz = (string)$part->ApparelSize->labelSize;
                    if ($sz && !in_array($sz, $sizes)) $sizes[] = $sz;
                }
                // Price (first found)
                if (!$price && isset($part->partPrice->PartPriceArray->PartPrice)) {
                    $pps = $part->partPrice->PartPriceArray->PartPrice;
                    if (!is_array($pps)) $pps = [$pps];
                    foreach ($pps as $pp) {
                        if (isset($pp->price) && (float)$pp->price > 0) {
                            $price = '$' . number_format((float)$pp->price, 2);
                            break;
                        }
                    }
                }
            }
        }

        // Primary image URL
        if (isset($prod->primaryImageUrl)) {
            $image = (string)$prod->primaryImageUrl;
        } elseif (isset($prod->ProductMarketingPointArray->ProductMarketingPoint)) {
            // fallback: first media URL if available
        }

        return [
            'id'          => $productId,
            'name'        => $name,
            'description' => $desc,
            'price'       => $price,
            'colors'      => $colors,
            'sizes'       => $sizes,
            'image'       => $image,
            'synced_at'   => date('c'),
        ];
    }
}
