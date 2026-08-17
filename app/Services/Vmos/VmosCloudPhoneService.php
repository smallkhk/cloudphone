<?php

namespace App\Services\Vmos;

/**
 * Typed wrapper around the subset of the VMOS Cloud OpenAPI this reseller site uses.
 * Full reference: https://cloud.vmoscloud.com/vmoscloud/doc/en/server/OpenAPI.html
 */
class VmosCloudPhoneService
{
    public function __construct(protected VmosClient $client) {}

    /**
     * SKU / package list ("Product Billing" catalogue), optionally filtered by Android
     * version or a comma-separated list of goodIds.
     */
    /**
     * SKU / package catalogue. Omit $androidVersion to take whatever VMOS
     * returns by default rather than filtering to a version that may not exist
     * on this account.
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

    /**
     * Short-lived token that lets a browser open a live, interactive session
     * with one specific cloud phone through the Web H5 SDK. Scoped to a single
     * padCode, so it can safely be handed to that device's owner.
     */
    public function stsToken(string $padCode): array
    {
        return $this->client->post('/vcpcloud/api/padApi/stsTokenByPadCode', ['padCode' => $padCode]);
    }

    /** Revokes a token issued by stsToken(). */
    public function clearStsToken(string $token): array
    {
        return $this->client->post('/vcpcloud/api/padApi/clearStsToken', ['token' => $token]);
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

    // --- Device identity -------------------------------------------------

    /**
     * Randomly regenerates SIM details (phone number, IMEI, IMSI, carrier) for
     * the given country. Restarts the instance.
     *
     * @param  array<string, string>  $props  Optional explicit Android props to force.
     */
    public function updateSim(string $padCode, ?string $countryCode = null, array $props = []): array
    {
        return $this->client->post('/vcpcloud/api/padApi/updateSIM', array_filter([
            'padCode' => $padCode,
            'countryCode' => $countryCode,
            'props' => $props ?: null,
        ], fn ($v) => $v !== null));
    }

    /** Countries supported by one-key-new-device / SIM generation. */
    public function countries(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/country');
    }

    /** @param  string[]  $padCodes */
    public function setGps(array $padCodes, float $latitude, float $longitude, ?float $altitude = null): array
    {
        return $this->client->post('/vcpcloud/api/padApi/gpsInjectInfo', array_filter([
            'padCodes' => $padCodes,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
        ], fn ($v) => $v !== null));
    }

    /** @param  string[]  $padCodes */
    public function setTimezone(array $padCodes, string $timeZone): array
    {
        return $this->client->post('/vcpcloud/api/padApi/updateTimeZone', [
            'padCodes' => $padCodes,
            'timeZone' => $timeZone,
        ]);
    }

    /** @param  string[]  $padCodes */
    public function setLanguage(array $padCodes, string $language, string $country = ''): array
    {
        return $this->client->post('/vcpcloud/api/padApi/updateLanguage', [
            'padCodes' => $padCodes,
            'language' => $language,
            'country' => $country,
        ]);
    }

    /**
     * "One-key new device" — wipes the instance and gives it a brand new hardware
     * identity, with SIM/GPS/timezone auto-written from the deployment region.
     *
     * @param  string[]  $padCodes
     */
    public function replaceNewDevice(array $padCodes, bool $wipeData = true, bool $keepLangTimezone = false): array
    {
        return $this->client->post('/vcpcloud/api/padApi/padReplaceNew', [
            'padCodes' => $padCodes,
            'setProxyFlag' => true,
            'wipeData' => $wipeData,
            'keepLangTimezone' => $keepLangTimezone,
        ]);
    }

    /**
     * Lower-level device replacement, letting you pin a country.
     *
     * @param  string[]  $padCodes
     */
    public function replacePad(array $padCodes, ?string $countryCode = null, bool $wipeData = true): array
    {
        return $this->client->post('/vcpcloud/api/padApi/replacePad', array_filter([
            'padCodes' => $padCodes,
            'countryCode' => $countryCode,
            'wipeData' => $wipeData,
        ], fn ($v) => $v !== null));
    }

    // --- Proxies ---------------------------------------------------------

    /** Static residential proxy products available to buy. */
    public function staticProxyGoods(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/proxyGoodList');
    }

    /** Countries/regions available for static residential proxies. */
    public function staticProxyRegions(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/getProxyRegion');
    }

    public function buyStaticProxy(int $proxyGoodId, string $country, string $proxyAddress, int $num = 1, bool $autoRenew = false): array
    {
        return $this->client->post('/vcpcloud/api/padApi/createProxyOrder', [
            'proxyGoodId' => $proxyGoodId,
            'region' => $country,
            'country' => $country,
            'proxyAddress' => $proxyAddress,
            'num' => $num,
            'autoRenew' => $autoRenew,
        ]);
    }

    /** Static residential proxies owned by this account (paginated). */
    public function listStaticProxies(int $page = 1, int $size = 50): array
    {
        return $this->client->post('/vcpcloud/api/padApi/queryProxyList', [
            'current' => $page,
            'size' => $size,
        ]);
    }

    public function deleteStaticProxy(string $host, int $port, string $account): array
    {
        return $this->client->post('/vcpcloud/api/padApi/delProxyByHost', [
            'host' => $host,
            'port' => $port,
            'account' => $account,
        ]);
    }

    /** Dynamic (traffic-billed) residential proxy products. */
    public function dynamicProxyGoods(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/getDynamicGoodService');
    }

    public function dynamicProxyRegions(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/getDynamicProxyRegion');
    }

    public function dynamicTrafficBalance(): array
    {
        return $this->client->get('/vcpcloud/api/padApi/queryCurrentTrafficBalance');
    }

    /** Attaches VMOS-managed proxies to instances. @param  string[]  $padCodes  @param  int[]  $proxyIds */
    public function attachProxies(array $padCodes, array $proxyIds): array
    {
        return $this->client->post('/vcpcloud/api/padApi/batchPadConfigProxy', [
            'padCodes' => $padCodes,
            'setProxyFlag' => true,
            'proxyIds' => $proxyIds,
        ]);
    }

    /**
     * Sets an arbitrary (customer-supplied) proxy on instances.
     *
     * @param  string[]  $padCodes
     */
    public function setCustomProxy(
        array $padCodes,
        string $ip,
        int $port,
        ?string $account = null,
        ?string $password = null,
        string $proxyName = 'socks5',
        string $proxyType = 'proxy',
        bool $enable = true,
    ): array {
        return $this->client->post('/vcpcloud/api/padApi/setProxy', array_filter([
            'padCodes' => $padCodes,
            'ip' => $ip,
            'port' => $port,
            'account' => $account,
            'password' => $password,
            'proxyName' => $proxyName,
            'proxyType' => $proxyType,
            'enable' => $enable,
            'model' => 'bypass',
        ], fn ($v) => $v !== null));
    }

    /** @param  string[]  $padCodes */
    public function disableProxy(array $padCodes): array
    {
        return $this->client->post('/vcpcloud/api/padApi/setProxy', [
            'padCodes' => $padCodes,
            'enable' => false,
        ]);
    }

    /** Checks whether a proxy is reachable and where it geolocates. */
    public function checkProxyIp(string $ip, int $port, ?string $account = null, ?string $password = null, string $proxyName = 'socks5'): array
    {
        return $this->client->post('/vcpcloud/api/padApi/checkIP', array_filter([
            'ip' => $ip,
            'port' => $port,
            'account' => $account,
            'password' => $password,
            'proxyName' => $proxyName,
        ], fn ($v) => $v !== null));
    }

    // --- Apps & files ----------------------------------------------------

    /** @param  string[]  $padCodes */
    public function listInstalledApps(array $padCodes): array
    {
        return $this->client->post('/vcpcloud/api/padApi/listInstalledApp', ['padCodes' => $padCodes]);
    }

    /**
     * Pushes a file (usually an APK) from a URL onto instances, optionally
     * installing it. Note: this endpoint's body is NOT part of the signature.
     *
     * @param  string[]  $padCodes
     */
    public function uploadFileFromUrl(array $padCodes, string $url, ?string $packageName = null, bool $autoInstall = true): array
    {
        return $this->client->post('/vcpcloud/api/padApi/uploadFileV3', array_filter([
            'padCodes' => $padCodes,
            'url' => $url,
            'autoInstall' => $autoInstall ? 1 : 0,
            'packageName' => $packageName,
        ], fn ($v) => $v !== null));
    }

    /** @param  string[]  $padCodes */
    public function startApp(array $padCodes, string $packageName): array
    {
        return $this->client->post('/vcpcloud/api/padApi/startApp', ['padCodes' => $padCodes, 'pkgName' => $packageName]);
    }

    /** @param  string[]  $padCodes */
    public function stopApp(array $padCodes, string $packageName): array
    {
        return $this->client->post('/vcpcloud/api/padApi/stopApp', ['padCodes' => $padCodes, 'pkgName' => $packageName]);
    }

    /** @param  string[]  $padCodes */
    public function restartApp(array $padCodes, string $packageName): array
    {
        return $this->client->post('/vcpcloud/api/padApi/restartApp', ['padCodes' => $padCodes, 'pkgName' => $packageName]);
    }

    /** @param  string[]  $padCodes */
    public function uninstallApp(array $padCodes, string $packageName): array
    {
        return $this->client->post('/vcpcloud/api/padApi/uninstallApp', ['padCodes' => $padCodes, 'pkgName' => $packageName]);
    }

    // --- ADB -------------------------------------------------------------

    /** @param  string[]  $padCodes */
    public function toggleAdb(array $padCodes, bool $enable): array
    {
        return $this->client->post('/vcpcloud/api/padApi/openOnlineAdb', [
            'padCodes' => $padCodes,
            'enable' => $enable,
        ]);
    }

    /** @param  string[]  $padCodes */
    public function adbInfo(array $padCodes): array
    {
        return $this->client->post('/vcpcloud/api/padApi/adb', ['padCodes' => $padCodes]);
    }

    // --- Email verification accounts --------------------------------------
    //
    // VMOS sells pre-made email accounts for service-registration flows
    // (GitHub, TikTok, …) with verification-code retrieval.
    //
    // Endpoints confirmed against VMOS's published OpenAPI spec (tag "Email
    // Verification Service") — five paths, matching CLAUDE.md. Full
    // request/response field names still aren't published the way they are
    // for the phone-plan endpoints, so parameter names follow the same
    // conventions used elsewhere in this API (goodId/goodNum-style purchase,
    // current/size-style pagination) but are NOT confirmed against a live
    // account. Check Admin → Diagnostics after adding credentials.

    /** Registration services email accounts are sold for (e.g. GitHub, TikTok). */
    public function emailServiceList(): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getEmailServiceList');
    }

    /** Purchasable email account types and remaining stock, optionally scoped to a service. */
    public function emailTypeList(?int $serviceId = null): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getEmailTypeList', array_filter([
            'serviceId' => $serviceId,
        ], fn ($v) => $v !== null));
    }

    /** Buys one or more pre-made email accounts of a given type. */
    public function createEmailOrder(int $emailTypeId, int $num = 1): array
    {
        return $this->client->post('/vcpcloud/api/padApi/createEmailOrder', [
            'emailTypeId' => $emailTypeId,
            'num' => $num,
        ]);
    }

    /** Email accounts already purchased on this account. */
    public function emailOrderList(?string $vmosOrderId = null, int $page = 1, int $size = 50): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getEmailOrder', array_filter([
            'orderId' => $vmosOrderId,
            'current' => $page,
            'size' => $size,
        ], fn ($v) => $v !== null));
    }

    /**
     * The verification code itself for a purchased email account. A separate
     * call from emailOrderList() in VMOS's spec — call this to poll for a
     * fresh code after triggering a "send code" on the target site.
     */
    public function emailCode(string $vmosOrderId): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getEmailCode', [
            'orderId' => $vmosOrderId,
        ]);
    }

    // --- SMS / phone verification numbers ----------------------------------
    //
    // VMOS's own console sells this bundled with the email service under
    // "Captcha Service" — a temporary phone number that receives a real SMS
    // verification code for app/service registrations. It is a genuine,
    // separately-billed product (confirmed from the VMOS console UI), NOT the
    // same thing as simulateSendSms (which only injects a fake SMS into a
    // device you already own).
    //
    // UNLIKE the email endpoints above, these paths do NOT appear anywhere in
    // VMOS's published OpenAPI docs, llms.txt quick reference, or raw spec tag
    // list as of this writing — only "Email Verification Service" is
    // documented as a tag. The method/path names below are a best-effort
    // mirror of the email endpoints' naming (VMOS is consistent about this
    // elsewhere in the API), but are UNCONFIRMED. Do not enable real customer
    // checkout for phone-number SKUs until these are verified to return real
    // data in Admin → Diagnostics — a wrong guess here could take a
    // customer's payment without delivering a working number.

    /** Registration services phone numbers are sold for. Mirrors emailServiceList(). */
    public function smsServiceList(): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getSmsServiceList');
    }

    /** Purchasable phone-number types (by country) and remaining stock. Mirrors emailTypeList(). */
    public function smsTypeList(?int $serviceId = null): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getSmsTypeList', array_filter([
            'serviceId' => $serviceId,
        ], fn ($v) => $v !== null));
    }

    /** Buys one or more temporary phone numbers of a given type. Mirrors createEmailOrder(). */
    public function createSmsOrder(int $smsTypeId, int $num = 1): array
    {
        return $this->client->post('/vcpcloud/api/padApi/createSmsOrder', [
            'smsTypeId' => $smsTypeId,
            'num' => $num,
        ]);
    }

    /** Phone numbers already purchased on this account. Mirrors emailOrderList(). */
    public function smsOrderList(?string $vmosOrderId = null, int $page = 1, int $size = 50): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getSmsOrder', array_filter([
            'orderId' => $vmosOrderId,
            'current' => $page,
            'size' => $size,
        ], fn ($v) => $v !== null));
    }

    /** The SMS verification code for a purchased number. Mirrors emailCode(). */
    public function smsCode(string $vmosOrderId): array
    {
        return $this->client->post('/vcpcloud/api/padApi/getSmsCode', [
            'orderId' => $vmosOrderId,
        ]);
    }
}
