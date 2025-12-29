import React from 'react';
import { View, StyleSheet, ViewStyle } from 'react-native';
import { useDeviceContext } from '../../hooks/useDeviceContext';

interface ResponsiveGridProps {
  children: React.ReactNode;
  columns?: {
    phone?: number;
    tablet?: number;
  };
  gap?: number;
  style?: ViewStyle;
}

/**
 * Responsive grid layout that adjusts columns based on device type
 * Phone: 1-2 columns (default: 1)
 * Tablet: 2-4 columns (default: 2)
 */
export const ResponsiveGrid: React.FC<ResponsiveGridProps> = ({
  children,
  columns = { phone: 1, tablet: 2 },
  gap = 16,
  style,
}) => {
  const { isTablet } = useDeviceContext();

  const numColumns = isTablet ? (columns.tablet || 2) : (columns.phone || 1);

  // Convert children to array for grid layout
  const childArray = React.Children.toArray(children);

  return (
    <View style={[styles.grid, { gap }, style]}>
      {childArray.map((child, index) => (
        <View
          key={index}
          style={[
            styles.gridItem,
            {
              width: `${100 / numColumns}%`,
              paddingRight: index % numColumns !== numColumns - 1 ? gap : 0,
            },
          ]}
        >
          {child}
        </View>
      ))}
    </View>
  );
};

const styles = StyleSheet.create({
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'flex-start',
  },
  gridItem: {
    boxSizing: 'border-box',
  },
});
