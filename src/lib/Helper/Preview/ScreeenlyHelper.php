<?php
/**
 * This file is part of the Passwords App
 * created by Marius David Wieschollek
 * and licensed under the AGPL.
 */

namespace OCA\Passwords\Helper\Preview;

use GuzzleHttp\Exception\ClientException;
use OCA\Passwords\Exception\ApiException;
use OCA\Passwords\Services\HelperService;
use OCA\Passwords\Services\WebsitePreviewService;
use Throwable;

/**
 * Class ScreeenlyHelper
 *
 * @package OCA\Passwords\Helper\Preview
 */
class ScreeenlyHelper extends AbstractPreviewHelper {

    const SCREEENLY_API_CONFIG_KEY = 'service/preview/screeenly/key';

    /**
     * @var string
     */
    protected string $prefix = HelperService::PREVIEW_SCREEENLY;

    /**
     * @param string $domain
     * @param string $view
     *
     * @return string
     * @throws ApiException
     */
    protected function getPreviewData(string $domain, string $view): string {
        [$serviceUrl, $serviceParams] = $this->getApiParams($domain, $view);

        try {
            $client   = $this->httpClientService->newClient();
            $response = $client->post($serviceUrl, ['json' => $serviceParams]);
        } catch(Throwable $e) {
            $code = $e instanceof ClientException ? "HTTP {$e->getResponse()->getStatusCode()}":$e->getMessage();
            $this->logger->error("Screeenly Request Failed, HTTP {$code}");
            throw new ApiException('API Request Failed', 502, $e);
        }

        $data = $response->getBody();
        if($data === null) {
            $this->logger->error("Screeenly Request Failed, HTTP {$response->getStatusCode()}");
            throw new ApiException('API Request Failed', 502);
        }

        $json = json_decode($data);
        if(isset($json->message)) {
            $this->logger->error("Screeenly {$json->title}: {$json->message}");
            throw new ApiException('API Request Failed', 502);
        }

        if(!isset($json->base64_raw)) {
            $this->logger->error("Screeenly did not return an image body");
            throw new ApiException('API Request Failed', 502);
        }

        return base64_decode($json->base64_raw);
    }

    /**
     * @param string $domain
     * @param string $view
     *
     * @return array
     * @throws ApiException
     */
    protected function getApiParams(string $domain, string $view): array {
        $apiKey = $this->config->getAppValue(self::SCREEENLY_API_CONFIG_KEY);

        $url = 'https://secure.screeenly.com/api/v1';
        if(preg_match('/^(http|https):\/\/(.+)\?key=(\w{50})$/', $apiKey, $matches)) {
            $url    = $matches[1].'://'.$matches[2];
            $apiKey = $matches[3];
        }

        if(strlen($apiKey) !== 50) {
            $this->logger->error("Screeenly API key is invalid");
            throw new ApiException('API Request Failed', 502);
        }

        $serviceUrl    = "{$url}/fullsize";
        $serviceParams = [
            'url'   => 'http://'.$domain,
            'key'   => $apiKey,
            'width' => $view === WebsitePreviewService::VIEWPORT_DESKTOP ? self::WIDTH_DESKTOP:self::WIDTH_MOBILE
        ];

        return [$serviceUrl, $serviceParams];
    }
}
