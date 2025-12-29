/**
 * Payout Calculator Service
 * Calculates tournament payouts based on finish position
 *
 * Constitution Requirements:
 * - US2-A1: Offline tournament CRUD operations
 * - Automatic payout calculations
 */

export interface PayoutStructure {
  type: 'fixed' | 'percentage';
  payouts: Payout[];
}

export interface Payout {
  position: number;
  amount?: number;
  percentage?: number;
}

export interface PayoutCalculatorConfig {
  /** Total number of players */
  totalPlayers: number;
  /** Total prize pool */
  prizePool: number;
  /** Payout structure */
  payoutStructure: PayoutStructure;
}

export interface PayoutResult {
  /** Position */
  position: number;
  /** Payout amount */
  amount: number;
  /** Percentage of prize pool */
  percentage: number;
}

/**
 * Payout Calculator
 *
 * Calculates tournament payouts:
 * - Fixed amounts per position
 * - Percentage-based payouts
 * - Common poker tournament structures
 */
export class PayoutCalculator {
  private config: PayoutCalculatorConfig;

  constructor(config: PayoutCalculatorConfig) {
    this.config = config;
  }

  /**
   * Calculate payout for a position
   */
  calculatePayout(position: number): PayoutResult {
    const { payoutStructure, prizePool } = this.config;

    if (payoutStructure.type === 'fixed') {
      const payout = payoutStructure.payouts.find((p) => p.position === position);
      if (!payout || !payout.amount) {
        return { position, amount: 0, percentage: 0 };
      }

      return {
        position,
        amount: payout.amount,
        percentage: (payout.amount / prizePool) * 100,
      };
    }

    // Percentage-based
    const payout = payoutStructure.payouts.find((p) => p.position === position);
    if (!payout || !payout.percentage) {
      return { position, amount: 0, percentage: 0 };
    }

    const amount = (prizePool * payout.percentage) / 100;

    return {
      position,
      amount,
      percentage: payout.percentage,
    };
  }

  /**
   * Calculate all payouts
   */
  calculateAllPayouts(): PayoutResult[] {
    const payouts: PayoutResult[] = [];

    for (const payout of this.config.payoutStructure.payouts) {
      const result = this.calculatePayout(payout.position);
      if (result.amount > 0) {
        payouts.push(result);
      }
    }

    return payouts.sort((a, b) => a.position - b.position);
  }

  /**
   * Generate standard payout structure
   */
  static generateStandardStructure(
    totalPlayers: number,
    prizePool: number
  ): PayoutStructure {
    // Standard poker payout: ~10% of players get paid
    const paidPositions = Math.max(1, Math.ceil(totalPlayers * 0.1));

    const payouts: Payout[] = [];

    if (paidPositions === 1) {
      payouts.push({ position: 1, percentage: 100 });
    } else if (paidPositions === 2) {
      payouts.push({ position: 1, percentage: 65 });
      payouts.push({ position: 2, percentage: 35 });
    } else if (paidPositions === 3) {
      payouts.push({ position: 1, percentage: 50 });
      payouts.push({ position: 2, percentage: 30 });
      payouts.push({ position: 3, percentage: 20 });
    } else if (paidPositions <= 5) {
      // Top 5 players
      const percentages = [40, 25, 15, 12, 8];
      for (let i = 0; i < paidPositions; i++) {
        payouts.push({ position: i + 1, percentage: percentages[i] });
      }
    } else if (paidPositions <= 10) {
      // Top 10 players
      const percentages = [30, 20, 14, 10, 7, 5, 4, 4, 3, 3];
      for (let i = 0; i < paidPositions; i++) {
        payouts.push({ position: i + 1, percentage: percentages[i] });
      }
    } else {
      // For larger fields, use declining percentages
      let remaining = 100;
      for (let i = 0; i < paidPositions; i++) {
        const percentage = Math.max(1, Math.floor(remaining / (paidPositions - i)));
        payouts.push({ position: i + 1, percentage });
        remaining -= percentage;
      }
    }

    return {
      type: 'percentage',
      payouts,
    };
  }

  /**
   * Calculate player's equity (expected value)
   */
  calculateEquity(currentPlayers: number, avgStackSize: number, playerStackSize: number): number {
    const { prizePool } = this.config;

    // Simple ICM (Independent Chip Model) calculation
    // Player's equity = (player chips / total chips) * prize pool
    const totalChips = currentPlayers * avgStackSize;
    const equity = (playerStackSize / totalChips) * prizePool;

    return Math.round(equity * 100) / 100;
  }

  /**
   * Get payout summary
   */
  getPayoutSummary(): {
    totalPaid: number;
    remaining: number;
    payoutCount: number;
    averagePayout: number;
  } {
    const payouts = this.calculateAllPayouts();

    const totalPaid = payouts.reduce((sum, p) => sum + p.amount, 0);
    const remaining = this.config.prizePool - totalPaid;
    const payoutCount = payouts.length;
    const averagePayout = payoutCount > 0 ? totalPaid / payoutCount : 0;

    return {
      totalPaid,
      remaining,
      payoutCount,
      averagePayout: Math.round(averagePayout * 100) / 100,
    };
  }

  /**
   * Update prize pool
   */
  updatePrizePool(newPrizePool: number): void {
    this.config.prizePool = newPrizePool;
  }

  /**
   * Update payout structure
   */
  updatePayoutStructure(newStructure: PayoutStructure): void {
    this.config.payoutStructure = newStructure;
  }
}

/**
 * Create a payout calculator instance
 */
export function createPayoutCalculator(config: PayoutCalculatorConfig): PayoutCalculator {
  return new PayoutCalculator(config);
}

/**
 * Quick calculation helper
 */
export function calculatePayout(
  position: number,
  totalPlayers: number,
  prizePool: number
): number {
  const structure = PayoutCalculator.generateStandardStructure(totalPlayers, prizePool);
  const calculator = new PayoutCalculator({
    totalPlayers,
    prizePool,
    payoutStructure: structure,
  });

  const result = calculator.calculatePayout(position);
  return result.amount;
}
