<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live Cricket Matches</title>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>
  <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      color: #2c3e50;
    }
    ul {
      list-style: none;
      padding-left: 0;
    }
    li {
      margin-bottom: 10px;
      padding: 10px;
      background: #f4f4f4;
      border-radius: 6px;
    }
  </style>
</head>
<body>
  <h2>📢 Live Cricket Matches</h2>
  <ul id="matches-container"></ul>

  <script>
    // Configure Pusher
    window.Pusher = Pusher;

    // Set up Laravel Echo
    window.Echo = new Echo({
      broadcaster: 'pusher',
      key: 'your-app-key', // Replace this
      cluster: 'your-app-cluster', // Replace this
      wsHost: window.location.hostname,
      wsPort: 6001, // For laravel-websockets
      forceTLS: false,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
    });

    // Subscribe to broadcast event
    Echo.channel('cricket-matches')
      .listen('CricketMatchesUpdated', (e) => {
        console.log('🔔 Match update received!', e);
        fetchMatches(); // Refresh list from server or use `e.data` directly
      });

    function fetchMatches() {
      axios.get('/api/matches')
        .then(response => {
          const matches = response.data || [];
          const container = document.getElementById('matches-container');
          container.innerHTML = '';

          matches.forEach(match => {
            const li = document.createElement('li');
            li.innerText = `${match.name} (${match.status})`;
            container.appendChild(li);
          });
        })
        .catch(error => console.error('Error fetching matches:', error));
    }

    // Initial load
    fetchMatches();
  </script>
</body>
</html>
