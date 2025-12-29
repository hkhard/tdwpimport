/**
 * Timer-related type definitions
 */
export type TimerEventType = 'tournament_start' | 'tournament_end' | 'level_start' | 'level_end' | 'break_start' | 'break_end' | 'pause' | 'resume' | 'director_override';
export interface BlindLevel {
    blindLevelId: string;
    level: number;
    smallBlind: number;
    bigBlind: number;
    ante?: number;
    duration: number;
    isBreak: boolean;
}
export interface TimerEvent {
    eventId: string;
    tournamentId: string;
    timestamp: Date;
    eventType: TimerEventType;
    previousState?: TimerEventState;
    newState: TimerEventState;
    deviceId?: string;
    metadata?: Record<string, unknown>;
}
export interface TimerEventState {
    level: number;
    elapsedTime: number;
    isRunning: boolean;
    isPaused: boolean;
}
export interface BlindSchedule {
    blindScheduleId: string;
    name: string;
    levels: BlindLevel[];
    breakIntervals: number[];
    startingStack: number;
    createdAt: Date;
    updatedAt: Date;
}
//# sourceMappingURL=timer.d.ts.map