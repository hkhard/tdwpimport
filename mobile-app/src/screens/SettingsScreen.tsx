/**
 * Settings Screen
 * App settings and preferences
 */

import { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, TextInput, ScrollView, Alert } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getApiBaseUrl, setApiBaseUrl } from '../services/api/tournamentApi';
import { getDefaultApiUrl } from '../config/api';

interface Props {
  onNavigateToBlindSchemes: () => void;
}

const API_URL_KEY = 'tdwp_api_url';
const DEFAULT_API_URL = getDefaultApiUrl();

export function SettingsScreen({ onNavigateToBlindSchemes }: Props) {
  const [apiUrl, setApiUrlInput] = useState(DEFAULT_API_URL);
  const [isEditingUrl, setIsEditingUrl] = useState(false);
  const [tempUrl, setTempUrl] = useState(DEFAULT_API_URL);

  // Load saved API URL on mount
  useEffect(() => {
    loadApiUrl();
  }, []);

  const loadApiUrl = async () => {
    try {
      const savedUrl = await AsyncStorage.getItem(API_URL_KEY);
      if (savedUrl) {
        const url = savedUrl;
        setApiUrlInput(url);
        setTempUrl(url);
        setApiBaseUrl(url);
      } else {
        setApiUrlInput(DEFAULT_API_URL);
        setTempUrl(DEFAULT_API_URL);
        setApiBaseUrl(DEFAULT_API_URL);
      }
    } catch (error) {
      console.error('[Settings] Failed to load API URL:', error);
    }
  };

  const handleSaveUrl = async () => {
    try {
      // Validate URL format
      let validatedUrl = tempUrl.trim();
      if (!validatedUrl.startsWith('http://') && !validatedUrl.startsWith('https://')) {
        validatedUrl = `http://${validatedUrl}`;
      }

      // Remove trailing slash
      validatedUrl = validatedUrl.replace(/\/$/, '');

      await AsyncStorage.setItem(API_URL_KEY, validatedUrl);
      setApiBaseUrl(validatedUrl);
      setApiUrlInput(validatedUrl);
      setIsEditingUrl(false);

      Alert.alert('Success', 'API URL updated successfully');
    } catch (error) {
      console.error('[Settings] Failed to save API URL:', error);
      Alert.alert('Error', 'Failed to save API URL');
    }
  };

  const handleResetUrl = async () => {
    try {
      await AsyncStorage.setItem(API_URL_KEY, DEFAULT_API_URL);
      setApiBaseUrl(DEFAULT_API_URL);
      setApiUrlInput(DEFAULT_API_URL);
      setTempUrl(DEFAULT_API_URL);
      setIsEditingUrl(false);
      Alert.alert('Success', 'API URL reset to default');
    } catch (error) {
      console.error('[Settings] Failed to reset API URL:', error);
    }
  };

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.title}>Settings</Text>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>API Configuration</Text>

        <View style={styles.urlContainer}>
          <Text style={styles.label}>Controller API URL</Text>

          {isEditingUrl ? (
            <View style={styles.urlEditContainer}>
              <TextInput
                style={styles.urlInput}
                value={tempUrl}
                onChangeText={setTempUrl}
                placeholder="http://localhost:3000/api"
                placeholderTextColor="#999"
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="url"
              />
              <View style={styles.urlButtonContainer}>
                <TouchableOpacity style={styles.urlButton} onPress={handleSaveUrl}>
                  <Text style={styles.urlButtonText}>Save</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.urlButton, styles.urlButtonCancel]}
                  onPress={() => {
                    setTempUrl(apiUrl);
                    setIsEditingUrl(false);
                  }}
                >
                  <Text style={styles.urlButtonTextCancel}>Cancel</Text>
                </TouchableOpacity>
              </View>
            </View>
          ) : (
            <View style={styles.urlDisplayContainer}>
              <Text style={styles.urlValue}>{apiUrl}</Text>
              <View style={styles.urlButtonContainer}>
                <TouchableOpacity style={styles.urlButton} onPress={() => setIsEditingUrl(true)}>
                  <Text style={styles.urlButtonText}>Edit</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.urlButton, styles.urlButtonCancel]}
                  onPress={handleResetUrl}
                >
                  <Text style={styles.urlButtonTextCancel}>Reset</Text>
                </TouchableOpacity>
              </View>
            </View>
          )}

          <Text style={styles.helpText}>
            Change this to connect to a different controller server.
          </Text>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Tournament Management</Text>

        <TouchableOpacity
          style={styles.setting}
          onPress={onNavigateToBlindSchemes}
        >
          <View style={styles.settingContent}>
            <Text style={styles.settingLabel}>Blind Level Management</Text>
            <Text style={styles.settingDescription}>Manage blind level schemes for tournaments</Text>
          </View>
          <Text style={styles.chevron}>{'›'}</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Preferences</Text>

        <TouchableOpacity style={styles.setting}>
          <Text style={styles.settingLabel}>Notifications</Text>
          <Text style={styles.settingValue}>Off</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.setting}>
          <Text style={styles.settingLabel}>Sound Effects</Text>
          <Text style={styles.settingValue}>On</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.footer}>
        <Text style={styles.version}>Version 1.0.0</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 32,
    fontWeight: '700',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  section: {
    marginTop: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    paddingHorizontal: 16,
    paddingVertical: 8,
    textTransform: 'uppercase',
  },
  urlContainer: {
    padding: 16,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
  },
  urlDisplayContainer: {
    marginBottom: 12,
  },
  urlValue: {
    fontSize: 14,
    color: '#007AFF',
    fontFamily: 'monospace',
    paddingVertical: 8,
  },
  urlEditContainer: {
    marginBottom: 12,
  },
  urlInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    fontFamily: 'monospace',
    backgroundColor: '#fafafa',
    marginBottom: 8,
  },
  urlButtonContainer: {
    flexDirection: 'row',
    gap: 8,
  },
  urlButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#007AFF',
    borderRadius: 6,
  },
  urlButtonCancel: {
    backgroundColor: '#f0f0f0',
  },
  urlButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  urlButtonTextCancel: {
    color: '#333',
    fontSize: 14,
    fontWeight: '600',
  },
  helpText: {
    fontSize: 12,
    color: '#999',
    marginTop: 4,
  },
  setting: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  settingContent: {
    flex: 1,
  },
  settingLabel: {
    fontSize: 16,
  },
  settingDescription: {
    fontSize: 13,
    color: '#666',
    marginTop: 2,
  },
  settingValue: {
    fontSize: 16,
    color: '#666',
  },
  chevron: {
    fontSize: 24,
    color: '#ccc',
    fontWeight: '300',
    marginLeft: 8,
  },
  footer: {
    padding: 20,
    marginTop: 20,
  },
  version: {
    fontSize: 12,
    color: '#999',
    textAlign: 'center',
  },
});
