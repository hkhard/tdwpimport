/**
 * Remote View Application
 * Handles SSE connection, real-time updates, and graceful degradation
 *
 * Features:
 * - SSE real-time updates (T099)
 * - Timer display component (T102)
 * - Leaderboard display (T103)
 * - Graceful degradation (T104)
 * - Viewer count tracking (T106)
 */

class TournamentRemoteView {
  constructor() {
    this.tournamentId = this.getTournamentId();
    this.controllerUrl = this.getControllerUrl();
    this.eventSource = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 10;
    this.reconnectDelay = 1000;
    this.viewerCount = 0;
    this.lastUpdate = null;

    // DOM elements
    this.elements = {
      tournamentName: document.getElementById('tournament-name'),
      statusBadge: document.getElementById('status-badge'),
      statusText: document.getElementById('status-text'),
      statusDot: document.querySelector('.status-dot'),
      currentLevel: document.getElementById('current-level'),
      timeRemaining: document.getElementById('time-remaining'),
      pauseIndicator: document.getElementById('pause-indicator'),
      smallBlind: document.getElementById('small-blind'),
      bigBlind: document.getElementById('big-blind'),
      ante: document.getElementById('ante'),
      leaderboardBody: document.getElementById('leaderboard-body'),
      connectionStatus: document.getElementById('connection-status'),
      lastUpdate: document.getElementById('last-update'),
      viewerCount: document.getElementById('viewer-count'),
      errorBanner: document.getElementById('error-banner'),
      errorMessage: document.getElementById('error-message'),
      retryButton: document.getElementById('retry-button'),
    };

    this.init();
  }

  getTournamentId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('tournament') || 'default';
  }

  getControllerUrl() {
    // Support both development and production
    const hostname = window.location.hostname;
    const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';

    // If served from controller, use same host
    // Otherwise, use configured controller URL
    if (hostname.includes('localhost') || hostname.includes('127.0.0.1')) {
      return `${protocol}//localhost:3000`;
    }

    return `${protocol}//${hostname}:3000`;
  }

  async init() {
    this.setupEventListeners();
    await this.loadInitialData();
    this.connectSSE();
  }

  setupEventListeners() {
    this.elements.retryButton.addEventListener('click', () => {
      this.hideError();
      this.connectSSE();
    });

    // Handle visibility change for viewer count (T106)
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        this.disconnect();
      } else {
        this.connectSSE();
      }
    });

    // Handle before unload
    window.addEventListener('beforeunload', () => {
      this.disconnect();
    });
  }

  async loadInitialData() {
    try {
      const response = await fetch(`${this.controllerUrl}/tournaments/${this.tournamentId}/public`);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      this.updateTournamentData(data);
    } catch (error) {
      console.error('Failed to load initial data:', error);
      this.showError('Unable to load tournament data. Retrying...');
    }
  }

  connectSSE() {
    if (this.eventSource) {
      this.eventSource.close();
    }

    const url = `${this.controllerUrl}/tournaments/${this.tournamentId}/stream`;
    this.eventSource = new EventSource(url);

    this.eventSource.addEventListener('open', () => {
      console.log('SSE connection opened');
      this.setConnectionStatus(true);
      this.reconnectAttempts = 0;
    });

    this.eventSource.addEventListener('error', (error) => {
      console.error('SSE connection error:', error);
      this.handleConnectionError();
    });

    this.eventSource.addEventListener('connected', (event) => {
      const data = JSON.parse(event.data);
      console.log('Connected to tournament:', data.tournamentId);
      this.incrementViewerCount();
    });

    this.eventSource.addEventListener('state', (event) => {
      const data = JSON.parse(event.data);
      this.updateTournamentData(data);
    });

    this.eventSource.addEventListener('update', (event) => {
      const data = JSON.parse(event.data);
      this.updateTournamentData(data);
      this.lastUpdate = new Date();
      this.updateLastUpdate();
    });

    this.eventSource.addEventListener('heartbeat', (event) => {
      const data = JSON.parse(event.data);
      console.log('Heartbeat received:', new Date(data.timestamp).toISOString());
    });

    this.eventSource.addEventListener('error', (event) => {
      if (!event.data) {
        this.handleConnectionError();
        return;
      }

      const data = JSON.parse(event.data);
      this.showError(data.message || 'Connection error occurred');
    });
  }

  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    this.setConnectionStatus(false);
  }

  handleConnectionError() {
    this.setConnectionStatus(false);

    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      // Exponential backoff for graceful degradation (T104)
      const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts);
      console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts + 1})`);

      setTimeout(() => {
        this.reconnectAttempts++;
        this.connectSSE();
      }, delay);
    } else {
      this.showError('Connection lost. Please refresh the page.');
    }
  }

  setConnectionStatus(connected) {
    if (connected) {
      this.elements.connectionStatus.textContent = 'Connected';
      this.elements.connectionStatus.className = 'status-value connected';
      this.elements.statusDot.className = 'status-dot connected';
      this.elements.statusText.textContent = 'Live';
    } else {
      this.elements.connectionStatus.textContent = 'Disconnected';
      this.elements.connectionStatus.className = 'status-value disconnected';
      this.elements.statusDot.className = 'status-dot disconnected';
      this.elements.statusText.textContent = 'Reconnecting...';
    }
  }

  updateTournamentData(data) {
    // Update header
    if (data.name) {
      this.elements.tournamentName.textContent = data.name;
    }

    // Update timer display (T102)
    if (data.currentLevel !== undefined) {
      this.elements.currentLevel.textContent = data.currentLevel;
    }

    if (data.clock) {
      this.updateClock(data.clock);
    }

    // Update blinds
    if (data.currentLevel) {
      this.updateBlinds(data.currentLevel);
    }

    // Update leaderboard (T103)
    if (data.players) {
      this.updateLeaderboard(data.players);
    }

    // Update status
    if (data.status) {
      this.updateTournamentStatus(data.status);
    }
  }

  updateClock(clock) {
    if (clock.remaining !== undefined) {
      const minutes = Math.floor(clock.remaining / 60);
      const seconds = clock.remaining % 60;
      this.elements.timeRemaining.textContent =
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    if (clock.paused !== undefined) {
      if (clock.paused) {
        this.elements.pauseIndicator.classList.add('visible');
      } else {
        this.elements.pauseIndicator.classList.remove('visible');
      }
    }
  }

  updateBlinds(level) {
    // This would typically come from the tournament structure
    // For now, using a simple calculation
    const smallBlind = Math.max(25, Math.floor(Math.pow(2, Math.floor(level / 5)) * 25));
    const bigBlind = smallBlind * 2;
    const ante = level > 10 ? Math.floor(smallBlind / 2) : 0;

    this.elements.smallBlind.textContent = this.formatNumber(smallBlind);
    this.elements.bigBlind.textContent = this.formatNumber(bigBlind);
    this.elements.ante.textContent = ante > 0 ? this.formatNumber(ante) : '-';
  }

  updateLeaderboard(players) {
    if (!players || players.length === 0) {
      this.elements.leaderboardBody.innerHTML = `
        <tr class="empty-row">
          <td colspan="4">Waiting for players...</td>
        </tr>
      `;
      return;
    }

    // Sort by stack (descending), eliminated players at bottom
    const sorted = [...players].sort((a, b) => {
      if (a.eliminated && b.eliminated) {
        return (a.finishOrder || 999) - (b.finishOrder || 999);
      }
      if (a.eliminated) return 1;
      if (b.eliminated) return -1;
      return (b.stack || 0) - (a.stack || 0);
    });

    this.elements.leaderboardBody.innerHTML = sorted.map((player, index) => `
      <tr class="${player.eliminated ? 'eliminated' : ''}">
        <td>${player.eliminated ? `#${player.finishOrder || '-'}` : index + 1}</td>
        <td>${this.escapeHtml(player.name)}</td>
        <td>${player.seat || '-'}</td>
        <td>${player.stack !== undefined ? this.formatNumber(player.stack) : '-'}</td>
      </tr>
    `).join('');
  }

  updateTournamentStatus(status) {
    const statusMap = {
      'scheduled': 'Scheduled',
      'running': 'In Progress',
      'paused': 'Paused',
      'completed': 'Completed',
    };

    this.elements.statusText.textContent = statusMap[status] || status;
  }

  updateLastUpdate() {
    if (this.lastUpdate) {
      const time = this.lastUpdate.toLocaleTimeString();
      this.elements.lastUpdate.textContent = time;
    }
  }

  incrementViewerCount() {
    this.viewerCount++;
    this.elements.viewerCount.textContent = this.viewerCount;
  }

  showError(message) {
    this.elements.errorMessage.textContent = message;
    this.elements.errorBanner.classList.remove('hidden');
  }

  hideError() {
    this.elements.errorBanner.classList.add('hidden');
  }

  formatNumber(num) {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => new TournamentRemoteView());
} else {
  new TournamentRemoteView();
}
