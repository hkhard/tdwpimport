/**
 * Viewer Tracker Service
 * Tracks connected viewers for tournaments with support for 100 concurrent viewers
 *
 * Constitution Requirements:
 * - US3-A4: 100 concurrent viewers per tournament
 * - US3-A2: Real-time viewer count updates
 */

export interface ViewerStats {
  tournamentId: string;
  authenticatedViewers: number;
  publicViewers: number;
  totalViewers: number;
  peakViewers: number;
  lastUpdated: Date;
}

export interface TournamentViewerSnapshot {
  tournamentId: string;
  timestamp: Date;
  viewerCount: number;
}

/**
 * Viewer Tracker Service
 *
 * Tracks viewer counts and provides statistics:
 * - Real-time viewer count per tournament
 * - Peak viewers tracking
 * - Historical snapshots
 */
export class ViewerTracker {
  private peakViewersByTournament = new Map<string, number>();
  private viewerHistory: TournamentViewerSnapshot[] = [];
  private readonly maxHistoryLength = 1000;
  private readonly snapshotInterval = 60000; // 1 minute

  /**
   * Record viewer count update
   */
  recordViewerCount(tournamentId: string, authenticatedCount: number, publicCount: number): void {
    const total = authenticatedCount + publicCount;

    // Update peak viewers
    const currentPeak = this.peakViewersByTournament.get(tournamentId) || 0;
    if (total > currentPeak) {
      this.peakViewersByTournament.set(tournamentId, total);
    }

    // Add to history
    this.viewerHistory.push({
      tournamentId,
      timestamp: new Date(),
      viewerCount: total,
    });

    // Trim history if needed
    if (this.viewerHistory.length > this.maxHistoryLength) {
      this.viewerHistory.shift();
    }
  }

  /**
   * Get current viewer stats for a tournament
   */
  getViewerStats(tournamentId: string, authenticatedCount: number, publicCount: number): ViewerStats {
    const total = authenticatedCount + publicCount;
    const peak = this.peakViewersByTournament.get(tournamentId) || total;

    return {
      tournamentId,
      authenticatedViewers: authenticatedCount,
      publicViewers: publicCount,
      totalViewers: total,
      peakViewers: peak,
      lastUpdated: new Date(),
    };
  }

  /**
   * Get peak viewers for a tournament
   */
  getPeakViewers(tournamentId: string): number {
    return this.peakViewersByTournament.get(tournamentId) || 0;
  }

  /**
   * Get viewer history for a tournament
   */
  getViewerHistory(tournamentId: string, limit = 100): TournamentViewerSnapshot[] {
    return this.viewerHistory
      .filter(snapshot => snapshot.tournamentId === tournamentId)
      .slice(-limit);
  }

  /**
   * Reset stats for a tournament
   */
  resetTournament(tournamentId: string): void {
    this.peakViewersByTournament.delete(tournamentId);
  }

  /**
   * Get all tournament IDs with active viewers
   */
  getActiveTournaments(): string[] {
    const activeTournaments = new Set<string>();
    for (const snapshot of this.viewerHistory) {
      // Only include tournaments from last 5 minutes
      if (Date.now() - snapshot.timestamp.getTime() < 5 * 60 * 1000) {
        activeTournaments.add(snapshot.tournamentId);
      }
    }
    return Array.from(activeTournaments);
  }

  /**
   * Get summary stats for all tournaments
   */
  getAllViewerStats(): Map<string, ViewerStats> {
    const stats = new Map<string, ViewerStats>();
    const activeTournaments = this.getActiveTournaments();

    for (const tournamentId of activeTournaments) {
      // Get latest authenticated and public counts from history
      const recentSnapshots = this.viewerHistory
        .filter(s => s.tournamentId === tournamentId)
        .slice(-1);

      if (recentSnapshots.length > 0) {
        stats.set(tournamentId, {
          tournamentId,
          authenticatedViewers: 0, // Would be populated by WebSocket tracker
          publicViewers: recentSnapshots[0].viewerCount,
          totalViewers: recentSnapshots[0].viewerCount,
          peakViewers: this.getPeakViewers(tournamentId),
          lastUpdated: recentSnapshots[0].timestamp,
        });
      }
    }

    return stats;
  }
}

/**
 * Singleton instance
 */
let viewerTrackerInstance: ViewerTracker | null = null;

export function getViewerTracker(): ViewerTracker {
  if (!viewerTrackerInstance) {
    viewerTrackerInstance = new ViewerTracker();
  }
  return viewerTrackerInstance;
}
