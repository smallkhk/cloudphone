<?php

namespace App\Services\Vmos;

/**
 * Typed wrapper around the subset of the VMOS Cloud OpenAPI this reseller site uses.
 * Full reference: https://cloud.vmoscloud.com/vmoscloud/doc/en/server/OpenAPI.html
 */
class VmosCloudPhoneService
{
    public function __construct(protected VmosClient $client)
    {
    }

    /**
     * SKU / package list ("Product Billing" catalogue), optionally filtered by Android
     * version or a comma-separated list of goodIds.
     */
    public function listGoods(?int $androidVersion = null, ?string $goodIds = null): array
    {
        return $this->client->get('/vcpcloud/api/padApi/getCloudGoodList', array_filter([
            'androidVersion' => $androidVersion,
            'goodIds' => $goodIds,
        ], fn ($v) => $v !== null));
    }

    /**
     * Purchase (or renew, when equipmentId is given) a cloud phone.
     *
     * @param  string|null  $equipmentId  Comma-separated device IDs to renew; omit to buy new.
     */
    public function createOrder(
        int $goodId,
        int $goodNum = 1,
        string $androidVersionName = 'Android13',
        bool $autoRenew = true,
        ?string $equipmentId = null,
        ?string $countryCode = null,
    ): array {
        return $this->client->post('/vcpcloud/api/padApi/createMoneyOrder', array_filter([
            'androidVersionName' => $androidVersionName,
            'goodId' => $goodId,
            'goodNum' => $goodNum,
            'autoRenew' => $autoRenew,
            'equipmentId' => $equipmentId,
            'countryCode' => $countryCode,
        ], fn ($v) => $v !== null));
    }

    /**
     * List cloud phones belonging to this AK/SK account (optionally filtered).
     *
     * @param  int[]|null  $equipmentIds
     */
    public function listPads(?string $padCode = null, ?array $equipmentIds = null): array
    {
        return $this->client->post('/vcpcloud/api/padApi/userPadList', array_filter([
            'padCode' => $padCode,
            'equipmentIds' => $equipmentIds,
        ], fn ($v) => $v !== null));
    }

    public function padInfo(string $padCode): array
    {
        return $this->client->post('/vcpcloud/api/padApi/padInfo', ['padCode' => $padCode]);
    }

    /** @param  string[]  $padCodes */
    public function restart(array $padCodes): array
    {
        return $this->client->post('/vcpcloud/api/padApi/restart', ['padCodes' => $padCodes]);
    }

    /** @param  string[]  $padCodes */
    public function reset(array $padCodes): array
    {
        return $this->client->post('/vcpcloud/api/padApi/reset', ['padCodes' => $padCodes]);
    }

    /**
     * Real-time screenshot URLs for one or more instances.
     *
     * @param  string[]  $padCodes
     */
    public function screenshot(array $padCodes, string $format = 'png'): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getLongGenerateUrl', [
            'padCodes' => $padCodes,
            'format' => $format,
        ]);
    }

    /** @param  int[]  $taskIds */
    public function taskDetail(array $taskIds): array
    {
        return $this->client->post('/vcpcloud/api/padApi/padTaskDetail', ['taskIds' => $taskIds]);
    }
}
