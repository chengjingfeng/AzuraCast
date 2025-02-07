<template>
    <div class="radio-player-widget">
        <div class="now-playing-details">
            <div class="now-playing-art" v-if="show_album_art && np.now_playing.song.art">
                <a v-bind:href="np.now_playing.song.art" data-fancybox target="_blank">
                    <img v-bind:src="np.now_playing.song.art" :alt="$t('album_art_alt')">
                </a>
            </div>
            <div class="now-playing-main">
                <div v-if="np.now_playing.song.title !== ''">
                    <h4 class="now-playing-title">{{ np.now_playing.song.title }}</h4>
                    <h5 class="now-playing-artist">{{ np.now_playing.song.artist }}</h5>
                </div>
                <div v-else>
                    <h4 class="now-playing-title">{{ np.now_playing.song.text }}</h4>
                </div>

                <div class="time-display" v-if="time_display_played">
                    <div class="time-display-played text-secondary">
                        {{ time_display_played }}
                    </div>
                    <div class="time-display-progress">
                        <div class="progress">
                            <div class="progress-bar bg-secondary" role="progressbar" v-bind:style="{ width: time_percent+'%' }"></div>
                        </div>
                    </div>
                    <div class="time-display-total text-secondary">
                        {{ time_display_total }}
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="radio-controls">
            <div class="radio-control-play-button" v-if="is_playing">
                <a href="#" role="button" :title="$t('pause_btn')" :aria-label="$t('pause_btn')" @click.prevent="toggle()">
                    <i class="material-icons lg" aria-hidden="true">pause_circle_filled</i>
                </a>
            </div>
            <div class="radio-control-play-button" v-else>
                <a href="#" role="button" :title="$t('play_btn')" :aria-label="$t('play_btn')" @click.prevent="toggle()">
                    <i class="material-icons lg" aria-hidden="true">play_circle_filled</i>
                </a>
            </div>

            <div class="radio-control-select-stream">
                <div v-if="this.streams.length > 1" class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="btn-select-stream" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ current_stream.name }}
                    </button>
                    <div class="dropdown-menu" aria-labelledby="btn-select-stream">
                        <a class="dropdown-item" v-for="stream in streams" href="javascript:;" @click="switchStream(stream)">
                            {{ stream.name }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="radio-control-mute-button">
                <a href="#" class="text-secondary" :title="$t('mute_btn')" @click.prevent="volume = 0">
                    <i class="material-icons" aria-hidden="true">volume_mute</i>
                </a>
            </div>
            <div class="radio-control-volume-slider">
                <input type="range" :title="$t('volume_slider')" class="custom-range" min="0" max="100" step="1" v-model="volume">
            </div>
            <div class="radio-control-max-volume-button">
                <a href="#" class="text-secondary" :title="$t('full_volume_btn')" @click.prevent="volume = 100">
                    <i class="material-icons" aria-hidden="true">volume_up</i>
                </a>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
.radio-player-widget {
    .now-playing-details {
        display: flex;
        align-items: center;

        .now-playing-art {
            padding-right: .5rem;

            img {
                width: 75px;
                height: auto;
                border-radius: 5px;

                @media (max-width:575px) {
                    width: 50px;
                }
            }
        }
        .now-playing-main {
            flex: 1;
            min-width: 0;
        }

        h4, h5 {
            margin: 0;
            line-height: 1.3;
        }
        h4 {
            font-size: 15px;
        }
        h5 {
            font-size: 13px;
            font-weight: normal;
        }

        .now-playing-title,
        .now-playing-artist {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;

            &:hover {
                text-overflow: clip;
                white-space: normal;
                word-break: break-all;
            }
        }

        .time-display {
            font-size: 10px;
            margin-top: .25rem;
            flex-direction: row;
            align-items: center;
            display: flex;

            .time-display-played {
                margin-right: .5rem;
            }
            .time-display-progress {
                flex: 1 1 auto;

                .progress-bar {
                    -webkit-transition: width 1s; /* Safari */
                    transition: width 1s;
                    transition-timing-function: linear;
                }
            }
            .time-display-total {
                margin-left: .5rem;
            }
        }
    }

    hr {
        margin-top: .5rem;
        margin-bottom: .5rem;
    }

    i.material-icons {
        line-height: 1;
    }

    .radio-controls {
        display: flex;
        flex-direction: row;
        align-items: center;

        .radio-control-play-button {
            margin-right: .25rem;
        }
        .radio-control-select-stream {
            flex: 1 1 auto;
        }

        .radio-control-mute-button,
        .radio-control-max-volume-button {
            flex-shrink: 0;
        }

        .radio-control-volume-slider {
            flex: 1 1 auto;
            max-width: 30%;

            input {
                height: 10px;
            }
        }
    }
}
</style>

<script>
import axios from 'axios';
import NchanSubscriber from 'nchan';
import store from 'store';

export default {
    props: {
        use_nchan: Boolean,
        now_playing_uri: String,
        show_album_art: Boolean
    },
    data: function() {
        return {
            "np": {
                "station": {
                    "listen_url": '',
                    "mounts": [],
                    "remotes": []
                },
                "now_playing": {
                    "song": {
                        "title": "Song Title",
                        "artist": "Song Artist",
                        "art": "",
                    },
                    "is_request": false,
                    "played_at": 0,
                    "elapsed": 0,
                    "duration": 0
                },
                "song_history": {},
            },
            "is_playing": false,
            "volume": 55,
            "current_stream": {
                "name": "",
                "url": ""
            },
            "audio": null,
            "np_timeout": null,
            "nchan_subscriber": null,
            "clock_interval": null
        };
    },
    created: function() {
        this.audio = document.createElement('audio');
        this.clock_interval = setInterval(this.iterateTimer, 1000);

        // Handle audio errors.
        this.audio.onerror = (e) => {
            if (e.target.error.code === e.target.error.MEDIA_ERR_NETWORK && this.audio.src !== '') {
                console.log('Network interrupted stream. Automatically reconnecting shortly...');
                setTimeout(this.play, 5000);
            }
        };

        this.audio.onended = () => {
            if (this.is_playing) {
                this.stop();

                console.log('Network interrupted stream. Automatically reconnecting shortly...');
                setTimeout(this.play, 5000);
            } else {
                this.stop();
            }
        };

        // Allow pausing from the mobile metadata update.
        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('pause', () => {
                this.stop();
            });
        }

        // Check webstorage for existing volume preference.
        if (store.enabled && store.get('player_volume') !== undefined) {
            this.volume = store.get('player_volume', this.volume);
        }

        // Check the query string if browser supports easy query string access.
        if (typeof URLSearchParams !== 'undefined') {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('volume')) {
                this.volume = parseInt(urlParams.get('volume'));
            }
        }

        this.checkNowPlaying();
    },
    computed: {
        "streams": function() {
            let all_streams = [];
            this.np.station.mounts.forEach(function (mount) {
                all_streams.push({
                    "name": mount.name,
                    "url": mount.url
                });
            });
            this.np.station.remotes.forEach(function(remote) {
                all_streams.push({
                    "name": remote.name,
                    "url": remote.url
                })
            });
            return all_streams;
        },
        "time_percent": function() {
            let time_played = this.np.now_playing.elapsed;
            let time_total = this.np.now_playing.duration;

            if (!time_total) {
                return 0;
            }
            if (time_played > time_total) {
                return 100;
            }

            return (time_played / time_total) * 100;
        },
        "time_display_played": function() {
            let time_played = this.np.now_playing.elapsed;
            let time_total = this.np.now_playing.duration;

            if (!time_total) {
                return null;
            }

            if (time_played > time_total) {
                time_played = time_total;
            }

            return this.formatTime(time_played);
        },
        "time_display_total": function() {
            let time_total = this.np.now_playing.duration;
            return (time_total) ? this.formatTime(time_total) : null;
        }
    },
    watch: {
        "volume": function(volume) {
            this.audio.volume = Math.min((Math.exp(volume/100)-1)/(Math.E-1), 1);

            if (store.enabled) {
                store.set('player_volume', volume);
            }
        },
    },
    methods: {
        "play": function() {
            this.audio.src = this.current_stream.url;
            this.audio.load();

            this.audio.play();

            this.is_playing = true;
        },
        "stop": function() {
            this.is_playing = false;

            this.audio.pause();
            this.audio.src = '';

            setTimeout(() => {
                this.audio.load();
            });
        },
        "toggle": function() {
            if (this.is_playing) {
                this.stop();
            } else {
                this.play();
            }
        },
        "switchStream": function(new_stream) {
            this.current_stream = new_stream;
            this.play();
        },
        "checkNowPlaying": function() {
            if (this.use_nchan) {
                this.nchan_subscriber = new NchanSubscriber(this.now_playing_uri);
                this.nchan_subscriber.on("message", (message, message_metadata) => {
                    this.handleNewNowPlaying(JSON.parse(message));
                });
                this.nchan_subscriber.start();
            } else {
                axios.get(this.now_playing_uri).then((response) => {
                    this.handleNewNowPlaying(response.data);
                }).catch((error) => {
                    console.error(error);
                }).then(() => {
                    clearTimeout(this.np_timeout);
                    this.np_timeout = setTimeout(this.checkNowPlaying, 15000);
                });
            }
        },
        "handleNewNowPlaying": function(np_new) {
            this.np = np_new;

            // Set a "default" current stream if none exists.
            if (this.current_stream.url === '' && np_new.station.listen_url !== '' && this.streams.length > 0) {
                let current_stream = null;

                this.streams.forEach(function(stream) {
                    if (stream.url === np_new.station.listen_url) {
                        current_stream = stream;
                    }
                });

                this.current_stream = current_stream;
            }

            // Update the browser metadata for browsers that support it (i.e. Mobile Chrome)
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: np_new.now_playing.song.title,
                    artist: np_new.now_playing.song.artist,
                    artwork: [
                        { src: np_new.now_playing.song.art }
                    ]
                });
            }

            Vue.prototype.$eventHub.$emit('np_updated', np_new);
        },
        "iterateTimer": function() {
            let current_time = Math.floor(Date.now() / 1000);
            let np_elapsed = current_time - this.np.now_playing.played_at;
            if (np_elapsed < 0) {
                np_elapsed = 0;
            } else if (np_elapsed >= this.np.now_playing.duration) {
                np_elapsed = this.np.now_playing.duration;
            }
            this.np.now_playing.elapsed = np_elapsed;
        },
        "formatTime": function(time) {
            let sec_num = parseInt(time, 10);

            let hours = Math.floor(sec_num / 3600);
            let minutes = Math.floor((sec_num - (hours * 3600)) / 60);
            let seconds = sec_num - (hours * 3600) - (minutes * 60);

            if (hours < 10) {
                hours = "0" + hours;
            }
            if (minutes < 10) {
                minutes = "0" + minutes;
            }
            if (seconds < 10) {
                seconds = "0" + seconds;
            }
            return (hours !== "00" ? hours + ':' : "") + minutes + ':' + seconds;
        }
    }
}
</script>
