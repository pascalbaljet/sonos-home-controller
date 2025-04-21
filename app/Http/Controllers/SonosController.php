<?php

namespace App\Http\Controllers;

use App\Sonos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SonosController
{
    /**
     * Cache TTL in minutes
     */
    protected const CACHE_TTL = 15;

    /**
     * Cache key for rooms data
     */
    protected const CACHE_KEY_ROOMS = 'sonos_rooms';

    /**
     * Create a new controller instance.
     */
    public function __construct(protected Sonos $sonos)
    {
        //
    }

    /**
     * Get all available rooms.
     */
    public function getRooms(): JsonResponse
    {
        try {
            $rooms = $this->getCachedRooms();

            return response()->json($rooms);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get rooms: '.$e->getMessage()], 500);
        }
    }

    /**
     * Play a stream on a specific room.
     */
    public function playStreamOnRoom(Request $request): JsonResponse
    {
        $request->validate([
            'roomName' => 'required|string',
            'streamUrl' => 'required|url',
        ]);

        try {
            $roomIp = $this->getRoomIp($request->input('roomName'));

            if (! $roomIp) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $success = $this->sonos->playStream(
                $roomIp,
                $request->input('streamUrl')
            );

            if ($success) {
                return response()->json(['message' => 'Stream started successfully']);
            }

            return response()->json(['error' => 'Failed to start stream'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error starting stream: '.$e->getMessage()], 500);
        }
    }

    /**
     * Stop playback on a specific room.
     */
    public function stop(Request $request): JsonResponse
    {
        $request->validate([
            'roomName' => 'required|string',
        ]);

        try {
            $roomIp = $this->getRoomIp($request->input('roomName'));

            if (! $roomIp) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $success = $this->sonos->stop($roomIp);

            if ($success) {
                return response()->json(['message' => 'Playback stopped successfully']);
            }

            return response()->json(['error' => 'Failed to stop playback'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error stopping playback: '.$e->getMessage()], 500);
        }
    }

    /**
     * Increase volume in a specific room.
     */
    public function volumeUp(Request $request): JsonResponse
    {
        $request->validate([
            'roomName' => 'required|string',
            'step' => 'integer|min:1|max:20',
        ]);

        try {
            $roomIp = $this->getRoomIp($request->input('roomName'));

            if (! $roomIp) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $step = $request->input('step', 5);
            $success = $this->sonos->volumeUp($roomIp, $step);

            if ($success) {
                $currentVolume = $this->sonos->getVolume($roomIp);

                return response()->json([
                    'message' => 'Volume increased successfully',
                    'currentVolume' => $currentVolume,
                ]);
            }

            return response()->json(['error' => 'Failed to increase volume'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error increasing volume: '.$e->getMessage()], 500);
        }
    }

    /**
     * Decrease volume in a specific room.
     */
    public function volumeDown(Request $request): JsonResponse
    {
        $request->validate([
            'roomName' => 'required|string',
            'step' => 'integer|min:1|max:20',
        ]);

        try {
            $roomIp = $this->getRoomIp($request->input('roomName'));

            if (! $roomIp) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $step = $request->input('step', 5);
            $success = $this->sonos->volumeDown($roomIp, $step);

            if ($success) {
                $currentVolume = $this->sonos->getVolume($roomIp);

                return response()->json([
                    'message' => 'Volume decreased successfully',
                    'currentVolume' => $currentVolume,
                ]);
            }

            return response()->json(['error' => 'Failed to decrease volume'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error decreasing volume: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get the current volume level of a specific room.
     */
    public function getVolume(Request $request): JsonResponse
    {
        $request->validate([
            'roomName' => 'required|string',
        ]);

        try {
            $roomIp = $this->getRoomIp($request->input('roomName'));

            if (! $roomIp) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            $volume = $this->sonos->getVolume($roomIp);

            if ($volume !== null) {
                return response()->json([
                    'volume' => $volume,
                ]);
            }

            return response()->json(['error' => 'Failed to get volume'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error getting volume: '.$e->getMessage()], 500);
        }
    }

    /**
     * Force refresh of the rooms cache.
     */
    public function refreshRooms(): JsonResponse
    {
        try {
            $this->clearRoomsCache();
            $rooms = $this->getCachedRooms(true);

            return response()->json([
                'message' => 'Rooms cache refreshed successfully',
                'rooms' => $rooms,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh rooms: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get rooms data from cache or fetch and cache if not present.
     *
     * @param  bool  $forceRefresh  Whether to force a refresh of the cached data
     */
    protected function getCachedRooms(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->clearRoomsCache();
        }

        return Cache::remember(self::CACHE_KEY_ROOMS, self::CACHE_TTL * 60, function () {
            return $this->sonos->getRooms();
        });
    }

    /**
     * Clear the rooms cache.
     */
    protected function clearRoomsCache(): void
    {
        Cache::forget(self::CACHE_KEY_ROOMS);
    }

    /**
     * Get the IP address for a room by name.
     */
    protected function getRoomIp(string $roomName): ?string
    {
        $rooms = $this->getCachedRooms();
        $room = $rooms[$roomName] ?? null;

        return $room ? $room['ip'] : null;
    }
}
