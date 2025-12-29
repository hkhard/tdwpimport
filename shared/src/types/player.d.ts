/**
 * Player-related type definitions
 */
export interface Player {
    playerId: string;
    name: string;
    email?: string;
    phone?: string;
    stats: PlayerStats;
    createdAt: Date;
    updatedAt: Date;
}
export interface PlayerStats {
    tournamentsPlayed: number;
    wins: number;
    totalWinnings: number;
    bustouts: number;
}
export interface TournamentPlayer {
    tournamentPlayerId: string;
    tournamentId: string;
    playerId: string;
    registrationTime: Date;
    startingStack: number;
    finishPosition?: number;
    winnings?: number;
    bustoutTime?: Date;
    eliminations?: number;
    tableName?: string;
    seatNumber?: number;
    updatedAt: Date;
}
export interface PlayerListItem {
    tournamentPlayerId: string;
    playerId: string;
    playerName: string;
    tableName?: string;
    seatNumber?: number;
    finishPosition?: number;
    isBusted: boolean;
}
//# sourceMappingURL=player.d.ts.map