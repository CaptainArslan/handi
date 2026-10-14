import { View, type StyleProp, type ViewStyle } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Spinner } from '@/components/ui/Spinner';
import { Txt } from '@/components/ui/Txt';
import { C } from '@/theme/tokens';

/** Centered spinner for a screen/section that is loading for the first time. */
export function Loading({ style }: { style?: StyleProp<ViewStyle> }) {
  return (
    <View style={[{ paddingVertical: 48, alignItems: 'center', justifyContent: 'center' }, style]} accessibilityLabel="Loading">
      <Spinner size={26} color={C.primary} track={C.dashed} width={3} />
    </View>
  );
}

/** Error with a retry button (only when there is no cached data to show). */
export function LoadError({ message, onRetry, style }: { message: string; onRetry: () => void; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={[{ paddingVertical: 40, paddingHorizontal: 16, alignItems: 'center', gap: 12 }, style]} accessibilityRole="alert">
      <View style={{ width: 56, height: 56, borderRadius: 18, backgroundColor: C.dangerSoft, alignItems: 'center', justifyContent: 'center' }}>
        <Icon name="alert" size={26} color={C.danger} />
      </View>
      <Txt v="bodyS" color={C.muted} align="center" style={{ maxWidth: 280 }}>
        {message}
      </Txt>
      <Button label="Try again" variant="outline" height={46} radius={14} fontSize={15} fill={false} icon="sync" onPress={onRetry} />
    </View>
  );
}
