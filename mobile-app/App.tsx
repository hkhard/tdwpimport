import { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import NetInfo from '@react-native-community/netinfo';
import { TournamentListScreen } from './src/screens/TournamentListScreen';
import { TimerScreen } from './src/screens/TimerScreen';
import { PlayerManagerScreen } from './src/screens/PlayerManagerScreen';
import { SettingsScreen } from './src/screens/SettingsScreen';
import { CreateTournamentScreen } from './src/screens/CreateTournamentScreen';
import TournamentDetailScreen from './src/screens/TournamentDetailScreen';
import { BlindSchemeListScreen } from './src/screens/BlindSchemeListScreen';
import { BlindSchemeDetailScreen } from './src/screens/BlindSchemeDetailScreen';
import { BlindSchemeEditorScreen } from './src/screens/BlindSchemeEditorScreen';
import { flushQueue, hasPendingItems } from './src/utils/syncQueue';
import { blindScheduleApi } from './src/services/api/blindScheduleApi';
import { useBlindScheduleStore } from './src/stores/blindScheduleStore';

type TabName = 'tournaments' | 'timer' | 'players' | 'settings';
type ScreenName = 'main' | 'createTournament' | 'tournamentDetail' | 'blindSchemeList' | 'blindSchemeDetail' | 'blindSchemeEditor';

export default function App() {
  const [activeTab, setActiveTab] = useState<TabName>('tournaments');
  const [currentScreen, setCurrentScreen] = useState<ScreenName>('main');
  const [selectedTournamentId, setSelectedTournamentId] = useState<string | null>(null);
  const [selectedSchemeId, setSelectedSchemeId] = useState<string | null>(null);
  const [isDuplicating, setIsDuplicating] = useState(false);

  // Setup NetInfo listener for offline detection and sync queue flushing
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(async (state) => {
      const isConnected = state.isConnected ?? false;

      if (isConnected) {
        // Just came back online - check if we have pending sync items
        const hasPending = await hasPendingItems();
        if (hasPending) {
          console.log('[App] Back online - flushing sync queue');
          try {
            const result = await flushQueue(blindScheduleApi);
            console.log(`[App] Sync queue flushed: ${result.processed} processed, ${result.failed} failed`);
          } catch (error) {
            console.error('[App] Error flushing sync queue:', error);
          }
        }
      } else {
        console.log('[App] Gone offline - changes will be queued');
      }
    });

    // Check for pending items on app start (in case we crashed while syncing)
    (async () => {
      const state = await NetInfo.fetch();
      if (state.isConnected ?? false) {
        const hasPending = await hasPendingItems();
        if (hasPending) {
          console.log('[App] App start - flushing pending sync queue');
          try {
            await flushQueue(blindScheduleApi);
          } catch (error) {
            console.error('[App] Error flushing sync queue on startup:', error);
          }
        }
      }
    })();

    return () => {
      unsubscribe();
    };
  }, []);

  const renderScreen = () => {
    if (currentScreen === 'createTournament') {
      return (
        <CreateTournamentScreen
          onComplete={() => {
            setCurrentScreen('main');
            setActiveTab('tournaments');
          }}
          onCancel={() => setCurrentScreen('main')}
          onTournamentCreated={(tournamentId) => {
            setSelectedTournamentId(tournamentId);
            setCurrentScreen('tournamentDetail');
          }}
        />
      );
    }

    if (currentScreen === 'tournamentDetail' && selectedTournamentId) {
      return (
        <TournamentDetailScreen
          tournamentId={selectedTournamentId}
          onBack={() => {
            setCurrentScreen('main');
            setSelectedTournamentId(null);
          }}
        />
      );
    }

    if (currentScreen === 'blindSchemeList') {
      return (
        <BlindSchemeListScreen
          onBack={() => setCurrentScreen('main')}
          onSchemePress={(schemeId) => {
            setSelectedSchemeId(schemeId);
            setCurrentScreen('blindSchemeDetail');
          }}
          onCreateScheme={() => setCurrentScreen('blindSchemeEditor')}
        />
      );
    }

    if (currentScreen === 'blindSchemeEditor') {
      return (
        <BlindSchemeEditorScreen
          mode={selectedSchemeId ? 'edit' : 'create'}
          schemeId={selectedSchemeId ?? undefined}
          onBack={() => {
            setCurrentScreen('blindSchemeList');
            setSelectedSchemeId(null);
          }}
          onSuccess={() => {
            // Refetch schedules to show the new/updated one
            // The store will handle this automatically via optimistic update
            setSelectedSchemeId(null);
          }}
        />
      );
    }

    if (currentScreen === 'blindSchemeDetail' && selectedSchemeId) {
      return (
        <BlindSchemeDetailScreen
          schemeId={selectedSchemeId}
          onBack={() => {
            setCurrentScreen('blindSchemeList');
            setSelectedSchemeId(null);
          }}
          onEdit={async (scheme) => {
            if (scheme.isDefault) {
              // Default scheme: duplicate first, then edit the copy
              setIsDuplicating(true);
              try {
                const { duplicateScheme } = useBlindScheduleStore.getState();
                const newScheme = await duplicateScheme(scheme.id, `${scheme.name} (Copy)`);
                setSelectedSchemeId(newScheme.id);
                setCurrentScreen('blindSchemeEditor');
              } catch (error) {
                console.error('Failed to duplicate scheme:', error);
                Alert.alert('Error', 'Failed to create copy of scheme');
              } finally {
                setIsDuplicating(false);
              }
            } else {
              // Custom scheme: edit directly
              setSelectedSchemeId(scheme.id);
              setCurrentScreen('blindSchemeEditor');
            }
          }}
          onDelete={() => {
            setCurrentScreen('blindSchemeList');
            setSelectedSchemeId(null);
          }}
        />
      );
    }

    switch (activeTab) {
      case 'tournaments':
        return (
          <TournamentListScreen
            onCreateTournament={() => setCurrentScreen('createTournament')}
            onTournamentPress={(tournamentId) => {
              setSelectedTournamentId(tournamentId);
              setCurrentScreen('tournamentDetail');
            }}
          />
        );
      case 'timer': return <TimerScreen />;
      case 'players': return <PlayerManagerScreen />;
      case 'settings':
        return (
          <SettingsScreen
            onNavigateToBlindSchemes={() => setCurrentScreen('blindSchemeList')}
          />
        );
    }
  };

  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.container}>
        <StatusBar style="auto" />
        <View style={styles.content}>{renderScreen()}</View>
        {currentScreen === 'main' && (
          <View style={styles.tabBar}>
            <TabButton label="Tournaments" active={activeTab === 'tournaments'} onPress={() => setActiveTab('tournaments')} />
            <TabButton label="Timer" active={activeTab === 'timer'} onPress={() => setActiveTab('timer')} />
            <TabButton label="Players" active={activeTab === 'players'} onPress={() => setActiveTab('players')} />
            <TabButton label="Settings" active={activeTab === 'settings'} onPress={() => setActiveTab('settings')} />
          </View>
        )}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

function TabButton({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <TouchableOpacity
      style={[styles.tabButton, active && styles.tabButtonActive]}
      onPress={onPress}
    >
      <Text style={[styles.tabButtonText, active && styles.tabButtonTextActive]}>{label}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    flex: 1,
  },
  tabBar: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
    backgroundColor: '#f8f8f8',
  },
  tabButton: {
    flex: 1,
    paddingVertical: 12,
    alignItems: 'center',
  },
  tabButtonActive: {
    backgroundColor: '#e0e0e0',
  },
  tabButtonText: {
    fontSize: 12,
    color: '#666',
  },
  tabButtonTextActive: {
    color: '#000',
    fontWeight: '600',
  },
});
