<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Sonos
{
    /**
     * SOAP service endpoints
     */
    private const SERVICE_RENDERING_CONTROL = 'RenderingControl';

    private const SERVICE_AV_TRANSPORT = 'AVTransport';

    private const SERVICE_ZONE_GROUP_TOPOLOGY = 'ZoneGroupTopology';

    /**
     * Default values
     */
    private const DEFAULT_VOLUME_STEP = 5;

    private const DEFAULT_DISCOVERY_TIMEOUT = 3;

    private const MAX_VOLUME = 100;

    private const MIN_VOLUME = 0;

    /**
     * Set the volume on a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @param  int  $volume  The desired volume (0-100)
     * @return bool Success status
     */
    public function setVolume(string $ip, int $volume): bool
    {
        $volume = $this->clampVolume($volume);

        $body = <<<XML
<u:SetVolume xmlns:u="urn:schemas-upnp-org:service:RenderingControl:1">
  <InstanceID>0</InstanceID>
  <Channel>Master</Channel>
  <DesiredVolume>{$volume}</DesiredVolume>
</u:SetVolume>
XML;

        $response = $this->sendSoapRequest($ip, self::SERVICE_RENDERING_CONTROL, 'SetVolume', $body);

        return $response !== null;
    }

    /**
     * Get the current volume of a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @return int|null The current volume or null if retrieval fails
     */
    public function getVolume(string $ip): ?int
    {
        $body = <<<'XML'
<u:GetVolume xmlns:u="urn:schemas-upnp-org:service:RenderingControl:1">
  <InstanceID>0</InstanceID>
  <Channel>Master</Channel>
</u:GetVolume>
XML;

        $response = $this->sendSoapRequest($ip, self::SERVICE_RENDERING_CONTROL, 'GetVolume', $body);

        if ($response && preg_match('/<CurrentVolume>(\d+)<\/CurrentVolume>/', $response, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Increase the volume on a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @param  int  $step  The amount to increase the volume by
     * @return bool Success status
     */
    public function volumeUp(string $ip, int $step = self::DEFAULT_VOLUME_STEP): bool
    {
        $current = $this->getVolume($ip);
        if ($current === null) {
            return false;
        }

        return $this->setVolume($ip, $current + $step);
    }

    /**
     * Decrease the volume on a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @param  int  $step  The amount to decrease the volume by
     * @return bool Success status
     */
    public function volumeDown(string $ip, int $step = self::DEFAULT_VOLUME_STEP): bool
    {
        $current = $this->getVolume($ip);
        if ($current === null) {
            return false;
        }

        return $this->setVolume($ip, $current - $step);
    }

    /**
     * Stop playback on a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @return bool Success status
     */
    public function stop(string $ip): bool
    {
        $body = <<<'XML'
<u:Stop xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
  <InstanceID>0</InstanceID>
</u:Stop>
XML;

        $response = $this->sendSoapRequest($ip, self::SERVICE_AV_TRANSPORT, 'Stop', $body);

        return $response !== null;
    }

    /**
     * Start playback on a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @return bool Success status
     */
    public function play(string $ip): bool
    {
        $body = <<<'XML'
<u:Play xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
  <InstanceID>0</InstanceID>
  <Speed>1</Speed>
</u:Play>
XML;

        $response = $this->sendSoapRequest($ip, self::SERVICE_AV_TRANSPORT, 'Play', $body);

        return $response !== null;
    }

    /**
     * Get the IP address of a Sonos device by room name
     *
     * @param  string  $roomName  The name of the room
     * @return string|null The IP address of the device or null if not found
     */
    public function getRoomIp(string $roomName): ?string
    {
        $devices = $this->getDevices();

        $device = $devices->first(function ($device) use ($roomName) {
            return $device['roomName'] === $roomName && $device['coordinator'] === true;
        });

        return $device['ip'] ?? null;
    }

    /**
     * Play a stream on a specific Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @param  string  $streamUrl  The URL of the stream to play
     * @return bool Success status
     */
    public function playStream(string $ip, string $streamUrl): bool
    {
        // Validate stream URL
        if (empty($streamUrl) || ! filter_var($streamUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        // XML-escape the URL to prevent XML injection
        $streamUrl = htmlspecialchars($streamUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $body = <<<XML
<u:SetAVTransportURI xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
  <InstanceID>0</InstanceID>
  <CurrentURI>{$streamUrl}</CurrentURI>
  <CurrentURIMetaData></CurrentURIMetaData>
</u:SetAVTransportURI>
XML;

        $response = $this->sendSoapRequest($ip, self::SERVICE_AV_TRANSPORT, 'SetAVTransportURI', $body);
        if ($response === null) {
            return false;
        }

        return $this->play($ip);
    }

    /**
     * Discover Sonos devices on the network
     *
     * @param  int  $timeout  The timeout for discovery in seconds
     * @return array The list of discovered device URLs
     */
    public function discoverDevices(int $timeout = self::DEFAULT_DISCOVERY_TIMEOUT): array
    {
        $timeout = max(1, $timeout); // Ensure minimum timeout of 1 second

        $msg = implode("\r\n", [
            'M-SEARCH * HTTP/1.1',
            'HOST:239.255.255.250:1900',
            'MAN:"ssdp:discover"',
            'MX:1',
            'ST:urn:schemas-upnp-org:device:ZonePlayer:1',
            '', '',
        ]);

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            return [];
        }

        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);

        $sent = socket_sendto($sock, $msg, strlen($msg), 0, '239.255.255.250', 1900);
        if ($sent === false) {
            socket_close($sock);

            return [];
        }

        $devices = [];

        while (true) {
            $buf = '';
            $from = '';
            $port = 0;
            $bytes = @socket_recvfrom($sock, $buf, 2048, 0, $from, $port);
            if ($bytes === false) {
                break;
            }

            if (Str::contains($buf, ['Sonos', 'ZonePlayer'])) {
                if (preg_match('/LOCATION:\s*(.*)\r\n/i', $buf, $match)) {
                    $location = trim($match[1]);
                    $devices[] = $location;
                }
            }
        }

        socket_close($sock);

        return array_unique($devices);
    }

    /**
     * Check if a device is a coordinator in its zone group
     *
     * @param  string  $deviceIp  The IP address of the device
     * @param  string  $uuid  The UUID of the device
     * @return bool Whether the device is a coordinator
     */
    public function isCoordinator(string $deviceIp, string $uuid): bool
    {
        if (empty($deviceIp) || empty($uuid)) {
            return false;
        }

        $endpoint = "http://{$deviceIp}:1400/ZoneGroupTopology/Control";

        // SOAP request XML
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <s:Body>
    <u:GetZoneGroupAttributes xmlns:u="urn:schemas-upnp-org:service:ZoneGroupTopology:1">
      <InstanceID>0</InstanceID>
    </u:GetZoneGroupAttributes>
  </s:Body>
</s:Envelope>
XML;

        try {
            // HTTP request using Laravel's HTTP facade
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset="utf-8"',
                'SOAPAction' => 'urn:schemas-upnp-org:service:ZoneGroupTopology:1#GetZoneGroupAttributes',
                'Content-Length' => strlen($xml),
            ])
                ->timeout(2)
                ->post($endpoint, $xml);

            $responseBody = $response->successful() ? $response->body() : '';

            // Parse the XML response
            if (empty($responseBody)) {
                return false;
            }

            preg_match('/<CurrentZoneGroupID>(.*?)<\/CurrentZoneGroupID>/', $responseBody, $groupMatch);
            preg_match('/<CurrentZonePlayerUUIDsInGroup>(.*?)<\/CurrentZonePlayerUUIDsInGroup>/', $responseBody, $uuidMatch);

            if (empty($groupMatch[1]) || empty($uuidMatch[1])) {
                return false;
            }

            $groupId = $groupMatch[1];
            $zonePlayerUUIDs = $uuidMatch[1];

            // Determine if this device is the coordinator
            // If there is only one player in the group, this is the coordinator
            if (strpos($zonePlayerUUIDs, ',') === false) {
                return true;
            }

            // Compare the UUID to determine the coordinator
            [$groupUuid] = explode(':', $groupId);

            return $groupUuid === $uuid;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get detailed information about a Sonos device
     *
     * @param  string  $url  The device description URL
     * @return array|null The device information or null if retrieval fails
     */
    public function getDeviceInfo(string $url): ?array
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout(2)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $xml = $response->body();
            $data = @simplexml_load_string($xml);

            if (! $data || ! isset($data->device)) {
                return null;
            }

            $device = $data->device;
            $ip = parse_url($url, PHP_URL_HOST);

            if (! $ip) {
                return null;
            }

            $uuid = Str::after((string) $device->UDN, 'uuid:');

            return [
                'coordinator' => $this->isCoordinator($ip, $uuid),
                'url' => $url,
                'ip' => $ip,
                'friendlyName' => (string) $device->friendlyName,
                'manufacturer' => (string) $device->manufacturer,
                'modelName' => (string) $device->modelName,
                'modelNumber' => (string) $device->modelNumber,
                'serialNumber' => (string) ($device->serialNum ?? ''),
                'roomName' => (string) $device->roomName,
                'udn' => (string) $device->UDN,
                'uuid' => $uuid,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get a collection of all discovered Sonos devices
     *
     * @return Collection Collection of device information
     */
    public function getDevices(): Collection
    {
        return collect($this->discoverDevices())
            ->map(function ($url) {
                return $this->getDeviceInfo($url);
            })
            ->filter();
    }

    /**
     * Get an array of room coordinators
     *
     * @return array Array of room coordinators keyed by room name
     */
    public function getRooms(): array
    {
        return $this->getDevices()
            ->filter(function ($device) {
                return $device['coordinator'] ?? false;
            })
            ->keyBy('roomName')
            ->all();
    }

    /**
     * Send a SOAP request to a Sonos device
     *
     * @param  string  $ip  The IP address of the Sonos device
     * @param  string  $service  The service name
     * @param  string  $action  The action name
     * @param  string  $body  The SOAP body
     * @return string|null The response body or null if the request fails
     */
    protected function sendSoapRequest(string $ip, string $service, string $action, string $body): ?string
    {
        if (empty($ip) || empty($service) || empty($action) || empty($body)) {
            return null;
        }

        $endpoint = "http://{$ip}:1400/MediaRenderer/{$service}/Control";

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <s:Body>
    {$body}
  </s:Body>
</s:Envelope>
XML;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset="utf-8"',
                'SOAPAction' => "\"urn:schemas-upnp-org:service:{$service}:1#{$action}\"",
            ])
                ->timeout(2)
                ->withBody($xml, 'text/xml')
                ->post($endpoint);

            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clamp volume value to the valid range
     *
     * @param  int  $volume  The volume to clamp
     * @return int The clamped volume value
     */
    private function clampVolume(int $volume): int
    {
        return max(self::MIN_VOLUME, min(self::MAX_VOLUME, $volume));
    }
}
