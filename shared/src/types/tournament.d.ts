/**
 * Tournament-related type definitions
 */
export type TournamentStatus = 'upcoming' | 'active' | 'completed' | 'cancelled';
export interface Tournament {
    tournamentId: string;
    name: string;
    description?: string;
    startTime: Date;
    endTime?: Date;
    status: TournamentStatus;
    currentBlindLevel: number;
    timerState: TimerState;
    prizePool?: number;
    payoutStructure?: PayoutStructure;
    blindScheduleId?: string;
    createdBy: string;
    createdAt: Date;
    updatedAt: Date;
}
export interface PayoutStructure {
    type: 'fixed' | 'percentage';
    payouts: Payout[];
}
export interface Payout {
    position: number;
    amount?: number;
    percentage?: number;
}
export interface TimerState {
    isRunning: boolean;
    isPaused: boolean;
    level: number;
    elapsedTime: number;
    remainingTime?: number;
    lastUpdateTime: Date;
}
//# sourceMappingURL=tournament.d.ts.map