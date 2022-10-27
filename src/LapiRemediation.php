<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

class LapiRemediation extends AbstractRemediation implements RemediationEngineInferface
{
    public function getIpRemediation(string $ip): string
    {
        $streamMode = $this->getConfig('stream_code');

        // Ask cache for Ip scoped decision
        $ipDecisions = $this->cacheStorage->retrieveDecisions('Ip', $ip);
        if(!$ipDecisions){
            $rawIpDecisions = [];
            if(!$streamMode){
                // Call LAPI
                $request = $this->client->request(
                    'GET',
                    '/v1/decisions',
                    ['ip' => $ip],
                    [
                        'User-Agent' => 'test',
                        'X-Api-Key' => $this->client->getConfig('api_key')
                    ]);
                $rawIpDecisions = $request;
            }
            // Store decisions (store a bypass if none)
            $ipDecisions = $this->storeDecisions('Ip', $ip, $rawIpDecisions);
        }
        // Geolocation if available
        $countryDecisions = [];
        if($this->getConfig('geolocation_enabled')){
            // Geolocation needs cache to store country result for a specific Ip
            $geolocation = new Geolocation($this->configs, $this->cacheStorage);
            $country = $geolocation->getCountryResult($ip);
            // Ask cache for Country scoped decision
            $countryDecisions = $this->cacheStorage->retrieveDecisions('Country', $country);
            if(!$countryDecisions) {
                $rawCountryDecisions = [];
                if(!$streamMode){
                    // Call LAPI
                    $rawCountryDecisions = $this->client->request(
                        'GET',
                        '/v1/decisions',
                        ['scope' => 'Country', 'value' => $country]
                    );
                }
                // Store decisions (store a bypass if none)
                $countryDecisions = $this->storeDecisions('Country', $country, $rawCountryDecisions);
            }

        }

        $decisions = array_merge($ipDecisions, $countryDecisions);

        return $decisions[0][0][0];
    }

}