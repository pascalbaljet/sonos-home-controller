<template>
    <div class="remote-container">
        <div class="remote">
            <div class="remote-header">
                <h1>Sonos Remote</h1>
                <div class="room-selector">
                    <label for="room-select">Room:</label>
                    <select id="room-select" v-model="selectedRoom" @change="fetchCurrentVolume">
                        <option v-for="room in rooms" :key="room" :value="room">{{ room }}</option>
                    </select>
                </div>
            </div>

            <div class="status-indicator" :class="{ active: isPlaying }">
                {{ isPlaying ? 'Playing' : 'Stopped' }}
            </div>

            <!-- Volume Display -->
            <div class="volume-display">
                <div class="volume-label">Volume</div>
                <div class="volume-bar-container">
                    <div class="volume-bar" :style="{ width: `${currentVolume}%` }"></div>
                </div>
                <div class="volume-value">{{ currentVolume }}</div>
            </div>

            <div class="button-grid">
                <button class="remote-button play-button" @click="startStream" :disabled="loading">
                    <i class="fas fa-play"></i>
                    <span>Play</span>
                </button>

                <button class="remote-button stop-button" @click="stopStream" :disabled="loading">
                    <i class="fas fa-stop"></i>
                    <span>Stop</span>
                </button>

                <button class="remote-button volume-up-button" @click="volumeUp" :disabled="loading">
                    <i class="fas fa-volume-up"></i>
                    <span>Volume Up</span>
                </button>

                <button class="remote-button volume-down-button" @click="volumeDown" :disabled="loading">
                    <i class="fas fa-volume-down"></i>
                    <span>Volume Down</span>
                </button>
            </div>

            <div v-if="lastAction" class="last-action">Last action: {{ lastAction }}</div>

            <div v-if="error" class="error-message">
                {{ error }}
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'SonosRemoteController',

    data() {
        return {
            rooms: [],
            selectedRoom: '',
            isPlaying: false,
            loading: false,
            lastAction: '',
            error: '',
            currentVolume: 0,
            streamUrl: 'http://192.168.0.34:8000/rapi.mp3.m3u',
            apiBaseUrl: '/api/sonos', // Base URL for your API
        };
    },

    mounted() {
        this.fetchRooms();
    },

    methods: {
        // Fetch available Sonos rooms
        async fetchRooms() {
            this.loading = true;
            this.error = '';

            try {
                const response = await axios.get(`${this.apiBaseUrl}/rooms`);
                this.rooms = Object.keys(response.data);

                if (this.rooms.length > 0) {
                    this.selectedRoom = this.rooms[0];
                    await this.fetchCurrentVolume();
                }
            } catch (err) {
                this.error = 'Failed to load rooms. Please check your connection.';
                console.error(err);
            } finally {
                this.loading = false;
            }
        },

        // Fetch current volume for selected room
        async fetchCurrentVolume() {
            if (!this.selectedRoom) {
                return;
            }

            try {
                const response = await axios.get(`${this.apiBaseUrl}/volume`, {
                    params: { roomName: this.selectedRoom },
                });

                this.currentVolume = response.data.volume;
            } catch (err) {
                console.error('Failed to fetch volume:', err);
                // Don't show error to user as this is a background operation
            }
        },

        // Start stream in selected room
        async startStream() {
            if (!this.selectedRoom) {
                this.error = 'Please select a room first';
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                await axios.post(`${this.apiBaseUrl}/playStreamOnRoom`, {
                    roomName: this.selectedRoom,
                    streamUrl: this.streamUrl,
                });

                this.isPlaying = true;
                this.lastAction = `Started stream in ${this.selectedRoom}`;
            } catch (err) {
                this.error = 'Failed to start stream. Please try again.';
                console.error(err);
            } finally {
                this.loading = false;
            }
        },

        // Stop playback in selected room
        async stopStream() {
            if (!this.selectedRoom) {
                this.error = 'Please select a room first';
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                await axios.post(`${this.apiBaseUrl}/stop`, {
                    roomName: this.selectedRoom,
                });

                this.isPlaying = false;
                this.lastAction = `Stopped playback in ${this.selectedRoom}`;
            } catch (err) {
                this.error = 'Failed to stop playback. Please try again.';
                console.error(err);
            } finally {
                this.loading = false;
            }
        },

        // Increase volume
        async volumeUp() {
            if (!this.selectedRoom) {
                this.error = 'Please select a room first';
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await axios.post(`${this.apiBaseUrl}/volumeUp`, {
                    roomName: this.selectedRoom,
                });

                this.lastAction = `Increased volume in ${this.selectedRoom}`;

                // Update current volume from response
                if (response.data.currentVolume !== undefined) {
                    this.currentVolume = response.data.currentVolume;
                } else {
                    // Fallback to fetching the volume if not returned in response
                    await this.fetchCurrentVolume();
                }
            } catch (err) {
                this.error = 'Failed to increase volume. Please try again.';
                console.error(err);
            } finally {
                this.loading = false;
            }
        },

        // Decrease volume
        async volumeDown() {
            if (!this.selectedRoom) {
                this.error = 'Please select a room first';
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await axios.post(`${this.apiBaseUrl}/volumeDown`, {
                    roomName: this.selectedRoom,
                });

                this.lastAction = `Decreased volume in ${this.selectedRoom}`;

                // Update current volume from response
                if (response.data.currentVolume !== undefined) {
                    this.currentVolume = response.data.currentVolume;
                } else {
                    // Fallback to fetching the volume if not returned in response
                    await this.fetchCurrentVolume();
                }
            } catch (err) {
                this.error = 'Failed to decrease volume. Please try again.';
                console.error(err);
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.remote-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: #f5f5f5;
}

.remote {
    width: 100%;
    max-width: 360px;
    background: linear-gradient(145deg, #2c3e50, #34495e);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow:
        0 10px 20px rgba(0, 0, 0, 0.3),
        0 6px 6px rgba(0, 0, 0, 0.25),
        inset 0 -2px 5px rgba(0, 0, 0, 0.2);
    color: white;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.remote-header {
    text-align: center;
}

.remote-header h1 {
    font-size: 1.8rem;
    margin: 0 0 1rem 0;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.room-selector {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.room-selector label {
    font-weight: 600;
}

.room-selector select {
    padding: 0.5rem;
    border-radius: 8px;
    border: none;
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 1rem;
    cursor: pointer;
}

.status-indicator {
    text-align: center;
    padding: 0.5rem;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.status-indicator.active {
    background-color: rgba(46, 204, 113, 0.6);
    box-shadow: 0 0 15px rgba(46, 204, 113, 0.5);
}

/* Volume Display Styles */
.volume-display {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    gap: 0.8rem;
}

.volume-label {
    font-weight: 600;
    font-size: 1rem;
    white-space: nowrap;
}

.volume-bar-container {
    flex-grow: 1;
    height: 10px;
    background-color: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
    overflow: hidden;
}

.volume-bar {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    border-radius: 5px;
    transition: width 0.3s ease;
}

.volume-value {
    font-weight: 600;
    min-width: 30px;
    text-align: center;
}

.button-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.remote-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: 16px;
    border: none;
    background: linear-gradient(145deg, #3a4f63, #2c3e50);
    box-shadow:
        0 4px 6px rgba(0, 0, 0, 0.3),
        inset 0 1px 1px rgba(255, 255, 255, 0.1);
    color: white;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.remote-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow:
        0 6px 8px rgba(0, 0, 0, 0.4),
        inset 0 1px 1px rgba(255, 255, 255, 0.2);
}

.remote-button:active:not(:disabled) {
    transform: translateY(1px);
    box-shadow:
        0 2px 3px rgba(0, 0, 0, 0.3),
        inset 0 1px 2px rgba(0, 0, 0, 0.3);
}

.remote-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.remote-button i {
    font-size: 1.5rem;
}

.play-button {
    background: linear-gradient(145deg, #2ecc71, #27ae60);
}

.stop-button {
    background: linear-gradient(145deg, #e74c3c, #c0392b);
}

.volume-up-button {
    background: linear-gradient(145deg, #3498db, #2980b9);
}

.volume-down-button {
    background: linear-gradient(145deg, #9b59b6, #8e44ad);
}

.last-action {
    text-align: center;
    padding: 0.5rem;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    font-size: 0.9rem;
}

.error-message {
    text-align: center;
    padding: 0.5rem;
    background-color: rgba(231, 76, 60, 0.3);
    border-radius: 8px;
    font-size: 0.9rem;
    color: #ffcccc;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .remote {
        padding: 1.2rem;
    }

    .remote-header h1 {
        font-size: 1.5rem;
    }

    .button-grid {
        gap: 0.8rem;
    }

    .remote-button {
        padding: 0.8rem;
    }

    .remote-button i {
        font-size: 1.3rem;
    }
}
</style>
