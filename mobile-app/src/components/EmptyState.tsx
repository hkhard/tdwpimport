import React from 'react';
import { View, Text, StyleSheet, Image } from 'react-native';
import { PlatformButton } from './PlatformButton';

/**
 * Empty State Component
 * Implements T134: Empty state components for better UX
 *
 * Features:
 * - Consistent empty state UI
 * - Icon/image support
 * - Action button support
 * - Different variants (no data, error, success)
 */
interface EmptyStateProps {
  title: string;
  message: string;
  icon?: React.ReactNode;
  action?: {
    label: string;
    onPress: () => void;
  };
  variant?: 'default' | 'info' | 'warning' | 'success';
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  message,
  icon,
  action,
  variant = 'default',
}) => {
  const variantStyles = styles[variant] || styles.default;

  return (
    <View style={styles.container}>
      <View style={[styles.content, variantStyles.container]}>
        {icon && <View style={styles.iconContainer}>{icon}</View>}
        <Text style={[styles.title, variantStyles.title]}>{title}</Text>
        <Text style={[styles.message, variantStyles.message]}>{message}</Text>
        {action && (
          <PlatformButton
            title={action.label}
            onPress={action.onPress}
            style={styles.actionButton}
          />
        )}
      </View>
    </View>
  );
};

// Pre-configured empty states
export const NoTournaments: React.FC<{ onCreate?: () => void }> = ({ onCreate }) => (
  <EmptyState
    title="No Tournaments"
    message="You haven't created any tournaments yet. Create your first tournament to get started."
    icon={<Text style={styles.icon}>🏆</Text>}
    action={onCreate ? { label: 'Create Tournament', onPress: onCreate } : undefined}
  />
);

export const NoPlayers: React.FC<{ onAdd?: () => void }> = ({ onAdd }) => (
  <EmptyState
    title="No Players"
    message="Register players to this tournament to begin tracking the action."
    icon={<Text style={styles.icon}>👥</Text>}
    action={onAdd ? { label: 'Add Player', onPress: onAdd } : undefined}
  />
);

export const NoNetwork: React.FC<{ onRetry?: () => void }> = ({ onRetry }) => (
  <EmptyState
    title="No Connection"
    message="Check your internet connection and try again."
    icon={<Text style={styles.icon}>📡</Text>}
    variant="warning"
    action={onRetry ? { label: 'Retry', onPress: onRetry } : undefined}
  />
);

export const SyncError: React.FC<{ onRetry?: () => void }> = ({ onRetry }) => (
  <EmptyState
    title="Sync Failed"
    message="Unable to sync your data. It will be retried automatically."
    icon={<Text style={styles.icon}>⚠️</Text>}
    variant="warning"
    action={onRetry ? { label: 'Retry Now', onPress: onRetry } : undefined}
  />
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    backgroundColor: '#F5F5F5',
  },
  content: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 32,
    alignItems: 'center',
    maxWidth: 400,
  },
  iconContainer: {
    marginBottom: 16,
  },
  icon: {
    fontSize: 64,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 12,
    textAlign: 'center',
  },
  message: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 24,
  },
  actionButton: {
    minWidth: 150,
  },
  default: {
    container: {},
    title: {},
    message: {},
  },
  info: {
    container: {
      backgroundColor: '#E3F2FD',
    },
    title: {
      color: '#1976D2',
    },
    message: {
      color: '#424242',
    },
  },
  warning: {
    container: {
      backgroundColor: '#FFF3E0',
    },
    title: {
      color: '#F57C00',
    },
    message: {
      color: '#424242',
    },
  },
  success: {
    container: {
      backgroundColor: '#E8F5E9',
    },
    title: {
      color: '#388E3C',
    },
    message: {
      color: '#424242',
    },
  },
});

export default EmptyState;
