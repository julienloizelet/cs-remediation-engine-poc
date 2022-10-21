<?php

interface CacheStorageInterface
{

    /**
     * @param string $scope
     * @param string $value
     * @return array
     */
    public function retrieveDecisions(string $scope, string $value): array;


    /**
     * @param string $scope
     * @param string $value
     * @param array $formattedDecision
     * @return bool // true on success, false otherwise
     *
     * Deletion will depend on  $formattedDecision['scope'] and $formattedDecision['value'] for cache key
     * and  $formattedDecision['id'] for the specific decision of item
     *
     */
    public function removeDecision(string $scope, string $value, array $formattedDecision): bool;

    /**
     * @param string $scope
     * @param string $value
     * @param array $formattedDecision
     * @return bool // true on success, false otherwise
     *
     * Cache key will depend on  $formattedDecision['scope'] and $formattedDecision['value']
     *
     * Storage will order decision by remediation priority
     *
     */
    public function storeDecision(string $scope, string $value, array $formattedDecision): bool;

}