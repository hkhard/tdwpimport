import { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { TournamentListScreen } from './src/screens/TournamentListScreen';
import { TimerScreen } from './src/screens/TimerScreen';
import { PlayerManagerScreen } from './src/screens/PlayerManagerScreen';
import { SettingsScreen } from './src/screens/SettingsScreen';
import { CreateTournamentScreen } from './src/screens/CreateTournamentScreen';
import TournamentDetailScreen from './src/screens/TournamentDetailScreen';

type TabName = 'tournaments' | 'timer' | 'players' | 'settings';
type ScreenName = 'main' | 'createTournament' | 'tournamentDetail';

export default function App() {
  const [activeTab, setActiveTab] = useState<TabName>('tournaments');
  const [currentScreen, setCurrentScreen] = useState<ScreenName>('main');
  const [selectedTournamentId, setSelectedTournamentId] = useState<string | null>(null);

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
      case 'settings': return <SettingsScreen />;
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
