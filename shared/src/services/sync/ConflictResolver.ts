/**
 * Conflict Resolution Service
 * Handles conflict detection and resolution strategies
 */

import type { Change, Conflict } from '../../types/sync';

export type ResolutionStrategy = 'last-write-wins' | 'client-wins' | 'server-wins' | 'manual';

export interface ConflictResolution {
  resolvedChange: Change;
  discardChanges: Change[];
}

export class ConflictResolver {
  private strategy: ResolutionStrategy;

  constructor(strategy: ResolutionStrategy = 'last-write-wins') {
    this.strategy = strategy;
  }

  /**
   * Detect conflict between local and server changes
   */
  detectConflict(localChange: Change, serverChange: Change): boolean {
    // Same entity, both modifications, different timestamps
    return (
      localChange.entityId === serverChange.entityId &&
      localChange.operation === 'update' &&
      serverChange.operation === 'update' &&
      localChange.timestamp !== serverChange.timestamp
    );
  }

  /**
   * Create conflict object
   */
  createConflict(localChange: Change, serverChange: Change): Conflict {
    return {
      conflictId: `conflict-${localChange.entityId}-${localChange.timestamp}`,
      entityType: localChange.entityType,
      entityId: localChange.entityId,
      localChange,
      serverChange,
      detectedAt: new Date().toISOString(),
    };
  }

  /**
   * Resolve conflict using configured strategy
   */
  resolve(conflict: Conflict, strategy?: ResolutionStrategy): ConflictResolution {
    const resolutionStrategy = strategy || this.strategy;

    switch (resolutionStrategy) {
      case 'last-write-wins':
        return this.lastWriteWins(conflict);
      case 'client-wins':
        return this.clientWins(conflict);
      case 'server-wins':
        return this.serverWins(conflict);
      case 'manual':
        throw new Error('Manual resolution requires user intervention');
      default:
        throw new Error(`Unknown resolution strategy: ${resolutionStrategy}`);
    }
  }

  /**
   * Last-write-wins: whichever change has the newer timestamp wins
   */
  private lastWriteWins(conflict: Conflict): ConflictResolution {
    const localTime = new Date(conflict.localChange.timestamp).getTime();
    const serverTime = new Date(conflict.serverChange.timestamp).getTime();

    if (localTime > serverTime) {
      return {
        resolvedChange: conflict.localChange,
        discardChanges: [conflict.serverChange],
      };
    } else {
      return {
        resolvedChange: conflict.serverChange,
        discardChanges: [conflict.localChange],
      };
    }
  }

  /**
   * Client always wins
   */
  private clientWins(conflict: Conflict): ConflictResolution {
    return {
      resolvedChange: conflict.localChange,
      discardChanges: [conflict.serverChange],
    };
  }

  /**
   * Server always wins
   */
  private serverWins(conflict: Conflict): ConflictResolution {
    return {
      resolvedChange: conflict.serverChange,
      discardChanges: [conflict.localChange],
    };
  }

  /**
   * Merge two changes if possible
   * Only works for non-conflicting field updates
   */
  attemptMerge(localChange: Change, serverChange: Change): Change | null {
    // Can only merge updates to same entity
    if (localChange.entityId !== serverChange.entityId) {
      return null;
    }

    // Parse data
    const localData = JSON.parse(localChange.data);
    const serverData = JSON.parse(serverChange.data);

    // Check for overlapping fields
    const localFields = new Set(Object.keys(localData));
    const serverFields = new Set(Object.keys(serverData));

    const overlapping = [...localFields].filter((field) => serverFields.has(field));

    // If fields overlap, can't auto-merge
    if (overlapping.length > 0) {
      return null;
    }

    // Merge data
    const mergedData = { ...serverData, ...localData };

    // Create merged change (uses server timestamp for authority)
    return {
      ...serverChange,
      data: JSON.stringify(mergedData),
    };
  }

  /**
   * Set resolution strategy
   */
  setStrategy(strategy: ResolutionStrategy): void {
    this.strategy = strategy;
  }

  /**
   * Get current strategy
   */
  getStrategy(): ResolutionStrategy {
    return this.strategy;
  }
}
