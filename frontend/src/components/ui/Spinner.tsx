import { useEffect } from 'react';
import Animated, { cancelAnimation, Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';

import { C } from '@/theme/tokens';

/** The design's ring spinner: 2.5px track with a contrasting top arc, .8s linear. */
export function Spinner({ size = 18, color = C.white, track = C.primaryMuted, width = 2.5 }: { size?: number; color?: string; track?: string; width?: number }) {
  const turn = useSharedValue(0);

  useEffect(() => {
    turn.value = withRepeat(withTiming(1, { duration: 800, easing: Easing.linear }), -1, false);
    return () => cancelAnimation(turn);
  }, [turn]);

  const style = useAnimatedStyle(() => ({ transform: [{ rotate: `${turn.value * 360}deg` }] }));

  return (
    <Animated.View
      accessibilityRole="progressbar"
      style={[
        { width: size, height: size, borderRadius: size / 2, borderWidth: width, borderColor: track, borderTopColor: color },
        style,
      ]}
    />
  );
}
