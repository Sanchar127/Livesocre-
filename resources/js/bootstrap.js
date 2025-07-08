
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.PUSHER_APP_KEY,
    cluster: import.meta.env.PUSHER_APP_CLUSTER,
    forceTLS: true,
});

window.Echo.channel('cricket-matches-channel')

    .listen('CricketMatchesUpdated', (e) => {
        console.log('New matches:', e.matches);
    });
