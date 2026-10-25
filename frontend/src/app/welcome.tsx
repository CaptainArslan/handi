import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';

import { HelpLink, TextLink } from '@/components/AuthKit';
import { ART } from '@/components/DishImage';
import { useBottom, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { Txt } from '@/components/ui/Txt';
import { C, S, tokens } from '@/theme/tokens';

function useFloat(distance: number, duration: number) {
  const y = useSharedValue(0);
  useEffect(() => {
    y.value = withRepeat(withTiming(-distance, { duration: duration / 2, easing: Easing.inOut(Easing.ease) }), -1, true);
  }, [y, distance, duration]);
  return y;
}

export default function Welcome() {
  const top = useTop(56);
  const bottom = useBottom(40);
  const a = useFloat(6, 4000);
  const b = useFloat(8, 4600);
  const left = useAnimatedStyle(() => ({ transform: [{ rotate: '-8deg' }, { translateY: a.value }] }));
  const right = useAnimatedStyle(() => ({ transform: [{ rotate: '6deg' }, { translateY: b.value }] }));

  return (
    <View style={[styles.screen, { paddingTop: top, paddingBottom: bottom }]}>
      <View style={styles.hero} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        <View style={styles.stage}>
          <View style={styles.circle} />
          <View style={styles.ring} />
          <Animated.View style={[styles.card, S.floatCard, { left: 34, top: 70, width: 150 }, left]}>
            <Image source={ART.bhindi} contentFit="cover" style={[styles.photo, { height: 108, backgroundColor: tokens.dishBg.bhindi }]} />
            <Txt v="itemTitle" size={15}>
              Bhindi
            </Txt>
          </Animated.View>
          <Animated.View style={[styles.card, S.floatCard, { right: 30, top: 40, width: 150 }, right]}>
            <Image source={ART.karahi} contentFit="cover" style={[styles.photo, { height: 108, backgroundColor: tokens.dishBg.karahi }]} />
            <Txt v="itemTitle" size={15}>
              Chicken Karahi
            </Txt>
          </Animated.View>
          <View style={[styles.card, styles.mainShadow, { left: 100, top: 168, width: 170 }]}>
            <Image source={ART.lentils} contentFit="cover" style={[styles.photo, { height: 124, backgroundColor: tokens.dishBg.lentils }]} />
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
              <Txt v="itemTitle" size={15}>
                Daal Chawal
              </Txt>
              <View style={styles.check}>
                <Icon name="check" size={14} color={C.white} strokeWidth={3} />
              </View>
            </View>
          </View>
        </View>
      </View>

      <View style={{ gap: 14, marginTop: 12 }}>
        <Txt v="h1Hero" accessibilityRole="header">
          3 dishes every morning. From your own kitchen.
        </Txt>
        <Txt v="bodyL" color={C.muted}>
          Picks skip what you cooked recently and fit today’s weather. One tap logs dinner, and tomorrow gets smarter.
        </Txt>
      </View>

      <View style={{ flex: 1 }} />
      <View style={{ gap: 14 }}>
        <Button label="Get started" weight={600} iconRight="arrowRight" iconSize={20} iconStroke={2} onPress={() => router.push('/setup/family')} />
        <Txt v="bodyS" color={C.muted} align="center">
          Takes about 2 minutes · <TextLink onPress={() => router.push('/login')}>I have an account</TextLink>
        </Txt>
        <HelpLink />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg, paddingHorizontal: 24 },
  hero: { height: 340, alignItems: 'center', justifyContent: 'center' },
  stage: { width: 342, height: 340, alignItems: 'center', justifyContent: 'center' },
  circle: { position: 'absolute', width: 320, height: 320, borderRadius: 160, backgroundColor: C.primary },
  ring: { position: 'absolute', width: 380, height: 380, borderRadius: 190, borderWidth: 2, borderStyle: 'dashed', borderColor: C.accent, opacity: 0.7 },
  card: { position: 'absolute', padding: 12, borderRadius: 22, backgroundColor: C.white, gap: 10 },
  photo: { width: '100%', borderRadius: 14 },
  mainShadow: { shadowColor: 'rgb(60,40,10)', shadowOpacity: 0.18, shadowRadius: 20, shadowOffset: { width: 0, height: 20 }, elevation: 12 },
  check: { width: 24, height: 24, borderRadius: 12, backgroundColor: C.primary, alignItems: 'center', justifyContent: 'center' },
});
