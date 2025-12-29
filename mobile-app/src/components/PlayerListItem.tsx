/**
 * Player List Item Component
 * Displays player with bustout recording functionality
 *
 * Constitution Requirements:
 * - US2-A1: Offline tournament CRUD operations
 * - Record finish position and update payouts
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  Modal,
  TextInput,
} from 'react-native';
import { PayoutCalculator, PayoutResult } from '../services/payout/PayoutCalculator';
import { TournamentPlayerRepository } from '../db/repositories/TournamentPlayerRepository';
import { getOfflineQueue } from '../services/sync/OfflineQueue';

export interface Player {
  playerId: string;
  name: string;
}

export interface TournamentPlayer {
  tournamentPlayerId: string;
  tournamentId: string;
  playerId: string;
  player: Player;
  startingStack: number;
  finishPosition?: number;
  winnings?: number;
  bustoutTime?: Date;
}

export interface PlayerListItemProps {
  player: TournamentPlayer;
  prizePool?: number;
  onUpdate?: () => void;
}

/**
 * Player List Item
 *
 * Displays player with bustout functionality:
 * - Shows player info and current status
 * - Bust out button with position/winnings entry
 * - Works offline, queues for sync
 */
export function PlayerListItem({ player, prizePool = 0, onUpdate }: PlayerListItemProps) {
  const [showBustoutModal, setShowBustoutModal] = useState(false);
  const [finishPosition, setFinishPosition] = useState('');
  const [winnings, setWinnings] = useState('');

  /**
   * Calculate suggested winnings based on position
   */
  function calculateSuggestedWinnings(): number {
    if (!finishPosition || !prizePool) {
      return 0;
    }

    const position = parseInt(finishPosition, 10);
    if (isNaN(position)) {
      return 0;
    }

    const calculator = new PayoutCalculator({
      totalPlayers: 0, // Would be passed from parent
      prizePool,
      payoutStructure: 'percentage',
      payouts: [],
    });

    const result = calculator.calculatePayout(position);
    return result.amount;
  }

  /**
   * Handle bustout submission
   */
  async function handleBustout() {
    const position = parseInt(finishPosition, 10);
    const winningsAmount = parseFloat(winnings);

    if (isNaN(position) || position <= 0) {
      Alert.alert('Error', 'Please enter valid finish position');
      return;
    }

    try {
      const tpRepo = new TournamentPlayerRepository();
      const queue = getOfflineQueue();

      // Update tournament player
      const updated = await tpRepo.update(player.tournamentPlayerId, {
        finishPosition: position,
        winnings: isNaN(winningsAmount) ? undefined : winningsAmount,
        bustoutTime: new Date(),
      });

      // Queue for sync
      await queue.enqueue({
        changeId: crypto.randomUUID(),
        entityType: 'tournament_player',
        operation: 'update',
        entityId: player.tournamentPlayerId,
        data: updated,
        timestamp: new Date(),
      });

      // Close modal and notify
      setShowBustoutModal(false);
      onUpdate?.();

      Alert.alert('Success', 'Bustout recorded');
    } catch (error) {
      console.error('Failed to record bustout:', error);
      Alert.alert('Error', 'Failed to record bustout');
    }
  }

  /**
   * Open bustout modal with suggested winnings
   */
  function openBustoutModal() {
    const suggested = calculateSuggestedWinnings();
    setFinishPosition('');
    setWinnings(suggested > 0 ? suggested.toString() : '');
    setShowBustoutModal(true);
  }

  return (
    <>
      <View style={styles.container}>
        {/* Player info */}
        <View style={styles.info}>
          <Text style={styles.name}>{player.player.name}</Text>
          <Text style={styles.details}>
            Stack: {player.startingStack}
          </Text>
        </View>

        {/* Status badge */}
        {player.finishPosition ? (
          <View style={styles.bustoutBadge}>
            <Text style={styles.bustoutBadgeText}>
              #{player.finishPosition}
            </Text>
            {player.winnings && (
              <Text style={styles.winningsText}>
                ${player.winnings}
              </Text>
            )}
          </View>
        ) : (
          <TouchableOpacity
            style={styles.bustoutButton}
            onPress={openBustoutModal}
          >
            <Text style={styles.bustoutButtonText}>Bust Out</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Bustout modal */}
      <Modal
        visible={showBustoutModal}
        transparent
        animationType="slide"
        onRequestClose={() => setShowBustoutModal(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>
              Record Bustout: {player.player.name}
            </Text>

            <Text style={styles.label}>Finish Position</Text>
            <TextInput
              style={styles.input}
              placeholder="e.g., 1"
              value={finishPosition}
              onChangeText={setFinishPosition}
              keyboardType="number-pad"
            />

            <Text style={styles.label}>Winnings (optional)</Text>
            <TextInput
              style={styles.input}
              placeholder="e.g., 1000"
              value={winnings}
              onChangeText={setWinnings}
              keyboardType="decimal-pad"
            />

            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, styles.cancelButton]}
                onPress={() => setShowBustoutModal(false)}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalButton, styles.confirmButton]}
                onPress={handleBustout}
              >
                <Text style={styles.confirmButtonText}>Record</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    marginBottom: 8,
  },
  info: {
    flex: 1,
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
  },
  details: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
  },
  bustoutBadge: {
    backgroundColor: '#E0E0E0',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    alignItems: 'center',
  },
  bustoutBadgeText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  winningsText: {
    fontSize: 12,
    color: '#4CAF50',
    marginTop: 2,
    fontWeight: '600',
  },
  bustoutButton: {
    backgroundColor: '#F44336',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  bustoutButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    width: '80%',
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    marginTop: 12,
    marginBottom: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 24,
  },
  modalButton: {
    flex: 1,
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButton: {
    backgroundColor: '#E0E0E0',
    marginRight: 8,
  },
  cancelButtonText: {
    color: '#666',
    fontSize: 16,
    fontWeight: '600',
  },
  confirmButton: {
    backgroundColor: '#4CAF50',
    marginLeft: 8,
  },
  confirmButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});

export default PlayerListItem;
