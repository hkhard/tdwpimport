/**
 * Player Manager Screen
 * Global player management - list, search, add, edit, delete
 */

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  FlatList,
  Modal,
  ActivityIndicator,
  Alert,
  RefreshControl,
} from 'react-native';
import { usePlayerStore } from '../stores/playerStore';
import type { Player } from '@shared/types/player';
import type { CreatePlayerInput, UpdatePlayerInput } from '../services/api/playerApi';

export function PlayerManagerScreen() {
  const { players, isLoading, error, loadPlayers, createPlayer, updatePlayer, deletePlayer } = usePlayerStore();

  const [searchQuery, setSearchQuery] = useState('');
  const [filteredPlayers, setFilteredPlayers] = useState<Player[]>([]);

  // Modal states
  const [addModalVisible, setAddModalVisible] = useState(false);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [editingPlayer, setEditingPlayer] = useState<Player | null>(null);

  // Form states
  const [formData, setFormData] = useState<CreatePlayerInput>({
    name: '',
    email: '',
    phone: '',
  });

  // Load players on mount
  useEffect(() => {
    loadPlayers();
  }, []);

  // Filter players based on search
  useEffect(() => {
    if (!searchQuery.trim()) {
      setFilteredPlayers(players);
    } else {
      const query = searchQuery.toLowerCase();
      setFilteredPlayers(
        players.filter(
          (p) =>
            p.name.toLowerCase().includes(query) ||
            p.email?.toLowerCase().includes(query) ||
            p.phone?.includes(query)
        )
      );
    }
  }, [searchQuery, players]);

  const handleRefresh = () => {
    loadPlayers();
  };

  const handleAddPlayer = async () => {
    if (!formData.name.trim()) {
      Alert.alert('Error', 'Player name is required');
      return;
    }

    try {
      await createPlayer({
        name: formData.name.trim(),
        email: formData.email?.trim() || undefined,
        phone: formData.phone?.trim() || undefined,
      });

      setAddModalVisible(false);
      setFormData({ name: '', email: '', phone: '' });
    } catch (error) {
      Alert.alert('Error', error instanceof Error ? error.message : 'Failed to create player');
    }
  };

  const handleEditPlayer = async () => {
    if (!editingPlayer || !formData.name.trim()) {
      Alert.alert('Error', 'Player name is required');
      return;
    }

    try {
      await updatePlayer(editingPlayer.playerId, {
        name: formData.name.trim(),
        email: formData.email?.trim() || undefined,
        phone: formData.phone?.trim() || undefined,
      });

      setEditModalVisible(false);
      setEditingPlayer(null);
      setFormData({ name: '', email: '', phone: '' });
    } catch (error) {
      Alert.alert('Error', error instanceof Error ? error.message : 'Failed to update player');
    }
  };

  const handleDeletePlayer = (player: Player) => {
    Alert.alert(
      'Delete Player',
      `Are you sure you want to delete "${player.name}"?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await deletePlayer(player.playerId);
            } catch (error) {
              Alert.alert('Error', error instanceof Error ? error.message : 'Failed to delete player');
            }
          },
        },
      ]
    );
  };

  const openEditModal = (player: Player) => {
    setEditingPlayer(player);
    setFormData({
      name: player.name,
      email: player.email || '',
      phone: player.phone || '',
    });
    setEditModalVisible(true);
  };

  const renderPlayerItem = ({ item }: { item: Player }) => (
    <View style={styles.playerItem}>
      <View style={styles.playerInfo}>
        <Text style={styles.playerName}>{item.name}</Text>
        {item.email && <Text style={styles.playerDetail}>{item.email}</Text>}
        {item.phone && <Text style={styles.playerDetail}>{item.phone}</Text>}
      </View>
      <View style={styles.playerActions}>
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() => openEditModal(item)}
        >
          <Text style={styles.actionButtonText}>Edit</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.actionButton, styles.deleteButton]}
          onPress={() => handleDeletePlayer(item)}
        >
          <Text style={[styles.actionButtonText, styles.deleteButtonText]}>Delete</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  const renderAddModal = () => (
    <Modal
      visible={addModalVisible}
      animationType="slide"
      transparent
      onRequestClose={() => setAddModalVisible(false)}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContent}>
          <Text style={styles.modalTitle}>Add New Player</Text>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Name *</Text>
            <TextInput
              style={styles.input}
              value={formData.name}
              onChangeText={(text) => setFormData({ ...formData, name: text })}
              placeholder="Enter player name"
              placeholderTextColor="#999"
              autoCapitalize="words"
            />
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Email</Text>
            <TextInput
              style={styles.input}
              value={formData.email}
              onChangeText={(text) => setFormData({ ...formData, email: text })}
              placeholder="player@email.com"
              placeholderTextColor="#999"
              keyboardType="email-address"
              autoCapitalize="none"
            />
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Phone</Text>
            <TextInput
              style={styles.input}
              value={formData.phone}
              onChangeText={(text) => setFormData({ ...formData, phone: text })}
              placeholder="(555) 123-4567"
              placeholderTextColor="#999"
              keyboardType="phone-pad"
            />
          </View>

          <View style={styles.modalButtons}>
            <TouchableOpacity
              style={[styles.modalButton, styles.cancelButton]}
              onPress={() => {
                setAddModalVisible(false);
                setFormData({ name: '', email: '', phone: '' });
              }}
            >
              <Text style={styles.cancelButtonText}>Cancel</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.modalButton, styles.saveButton]}
              onPress={handleAddPlayer}
              disabled={isLoading}
            >
              <Text style={styles.saveButtonText}>Add Player</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );

  const renderEditModal = () => (
    <Modal
      visible={editModalVisible}
      animationType="slide"
      transparent
      onRequestClose={() => setEditModalVisible(false)}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContent}>
          <Text style={styles.modalTitle}>Edit Player</Text>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Name *</Text>
            <TextInput
              style={styles.input}
              value={formData.name}
              onChangeText={(text) => setFormData({ ...formData, name: text })}
              placeholder="Enter player name"
              placeholderTextColor="#999"
              autoCapitalize="words"
            />
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Email</Text>
            <TextInput
              style={styles.input}
              value={formData.email}
              onChangeText={(text) => setFormData({ ...formData, email: text })}
              placeholder="player@email.com"
              placeholderTextColor="#999"
              keyboardType="email-address"
              autoCapitalize="none"
            />
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Phone</Text>
            <TextInput
              style={styles.input}
              value={formData.phone}
              onChangeText={(text) => setFormData({ ...formData, phone: text })}
              placeholder="(555) 123-4567"
              placeholderTextColor="#999"
              keyboardType="phone-pad"
            />
          </View>

          <View style={styles.modalButtons}>
            <TouchableOpacity
              style={[styles.modalButton, styles.cancelButton]}
              onPress={() => {
                setEditModalVisible(false);
                setEditingPlayer(null);
                setFormData({ name: '', email: '', phone: '' });
              }}
            >
              <Text style={styles.cancelButtonText}>Cancel</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.modalButton, styles.saveButton]}
              onPress={handleEditPlayer}
              disabled={isLoading}
            >
              <Text style={styles.saveButtonText}>Save</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );

  if (isLoading && players.length === 0) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Loading players...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header with search and add */}
      <View style={styles.header}>
        <View style={styles.searchContainer}>
          <TextInput
            style={styles.searchInput}
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholder="Search players..."
            placeholderTextColor="#999"
            autoCapitalize="none"
          />
        </View>
        <TouchableOpacity
          style={styles.addButton}
          onPress={() => setAddModalVisible(true)}
        >
          <Text style={styles.addButtonText}>+ Add</Text>
        </TouchableOpacity>
      </View>

      {/* Error message */}
      {error && (
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity onPress={handleRefresh}>
            <Text style={styles.retryText}>Retry</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Player list */}
      {filteredPlayers.length === 0 ? (
        <View style={styles.center}>
          <Text style={styles.emptyText}>
            {searchQuery ? 'No players found' : 'No players yet'}
          </Text>
          {!searchQuery && (
            <TouchableOpacity
              style={styles.emptyButton}
              onPress={() => setAddModalVisible(true)}
            >
              <Text style={styles.emptyButtonText}>Add Your First Player</Text>
            </TouchableOpacity>
          )}
        </View>
      ) : (
        <FlatList
          data={filteredPlayers}
          keyExtractor={(item) => item.playerId}
          renderItem={renderPlayerItem}
          refreshControl={
            <RefreshControl
              refreshing={isLoading}
              onRefresh={handleRefresh}
              colors={['#007AFF']}
            />
          }
          contentContainerStyle={styles.listContent}
        />
      )}

      {/* Modals */}
      {renderAddModal()}
      {renderEditModal()}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  loadingText: {
    fontSize: 16,
    color: '#666',
    marginTop: 12,
  },
  header: {
    flexDirection: 'row',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    backgroundColor: '#f8f8f8',
    gap: 12,
  },
  searchContainer: {
    flex: 1,
  },
  searchInput: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 16,
  },
  addButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    justifyContent: 'center',
  },
  addButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  errorContainer: {
    backgroundColor: '#FFE5E5',
    padding: 12,
    margin: 16,
    borderRadius: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  errorText: {
    color: '#FF3B30',
    fontSize: 14,
    flex: 1,
  },
  retryText: {
    color: '#007AFF',
    fontSize: 14,
    fontWeight: '600',
  },
  emptyText: {
    fontSize: 18,
    color: '#666',
    marginBottom: 20,
    textAlign: 'center',
  },
  emptyButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  emptyButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  listContent: {
    paddingVertical: 8,
  },
  playerItem: {
    flexDirection: 'row',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
    alignItems: 'center',
  },
  playerInfo: {
    flex: 1,
  },
  playerName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
  },
  playerDetail: {
    fontSize: 14,
    color: '#666',
    marginTop: 2,
  },
  playerActions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    backgroundColor: '#007AFF',
  },
  deleteButton: {
    backgroundColor: '#FF3B30',
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  deleteButtonText: {
    color: '#fff',
  },
  // Modal styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    width: '100%',
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 20,
    color: '#000',
  },
  formGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 16,
    backgroundColor: '#fafafa',
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 20,
    gap: 12,
  },
  modalButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButton: {
    backgroundColor: '#f0f0f0',
  },
  saveButton: {
    backgroundColor: '#007AFF',
  },
  cancelButtonText: {
    color: '#333',
    fontSize: 16,
    fontWeight: '600',
  },
  saveButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
