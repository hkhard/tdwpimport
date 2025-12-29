/**
 * App Navigator
 * Stack navigation for Settings and related screens
 *
 * NOTE: This file is currently not used by the app.
 * The app uses a custom navigation system in App.tsx with callback props.
 */

import { createStackNavigator } from '@react-navigation/stack';
import { SettingsScreen } from '../screens/SettingsScreen';
import { BlindSchemeListScreen } from '../screens/BlindSchemeListScreen';
import BlindSchemeDetailScreen from '../screens/BlindSchemeDetailScreen';

export type RootStackParamList = {
  Settings: undefined;
  BlindSchemeList: undefined;
  BlindSchemeDetail: { schemeId: string };
};

const Stack = createStackNavigator<RootStackParamList>();

export function AppNavigator() {
  return (
    <Stack.Navigator
      initialRouteName="Settings"
      screenOptions={{
        headerStyle: {
          backgroundColor: '#fff',
        },
        headerTintColor: '#000',
        headerTitleStyle: {
          fontWeight: '600',
        },
      }}
    >
      <Stack.Screen
        name="Settings"
        component={SettingsScreen}
        options={{ title: 'Settings' }}
      />
      <Stack.Screen
        name="BlindSchemeList"
        component={BlindSchemeListScreen}
        options={{ title: 'Blind Level Schemes' }}
      />
      <Stack.Screen
        name="BlindSchemeDetail"
        component={BlindSchemeDetailScreen}
        options={{ title: 'Scheme Details' }}
      />
    </Stack.Navigator>
  );
}
