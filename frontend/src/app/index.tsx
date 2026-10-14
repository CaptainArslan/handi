import { router, type Href } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, {
  Easing,
  interpolate,
  useAnimatedProps,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withTiming,
  type SharedValue,
} from 'react-native-reanimated';
import Svg, { Circle, Ellipse, G, Path, Rect } from 'react-native-svg';

import { isNetworkError, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk } from '@/api/queries';
import { downloadLibrary, hasLibrary } from '@/components/AuthKit';
import { useBottom, useTop } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Txt } from '@/components/ui/Txt';
import { queryClient } from '@/lib/query-client';
import { useAuth } from '@/store/auth';
import { useNet } from '@/store/net';
import { C } from '@/theme/tokens';

const STATUS = ['Warming up the kitchen…', 'Checking your session…', 'Syncing the dish library…', 'Ready'];

const AnimatedPath = Animated.createAnimatedComponent(Path);
const AnimatedRect = Animated.createAnimatedComponent(Rect);
const AnimatedG = Animated.createAnimatedComponent(G);

/** Where the app should open: Today (session), No connection (first launch offline) or Welcome. */
async function decideRoute(): Promise<Href> {
  const online = await useNet
    .getState()
    .recheck()
    .catch(() => true);

  if (useAuth.getState().token) {
    if (!online) return '/today'; // offline mode with the saved session
    try {
      const me = await queryClient.fetchQuery({ queryKey: qk.me, queryFn: endpoints.me, staleTime: 0 });
      useAuth.getState().setUser(me);
      return '/today';
    } catch (error) {
      // 401 → the interceptor dropped the token; continue as a guest. Anything else: keep the session.
      if (isNetworkError(error) || status(error) !== 401) return '/today';
    }
  }

  const libraryOk = await hasLibrary();
  if (!libraryOk && !online) return '/no-connection';
  const downloaded = await downloadLibrary();
  if (!downloaded && !libraryOk) return '/no-connection';
  return '/welcome';
}

export default function Splash() {
  const top = useTop(120);
  const bottom = useBottom(56);
  const [step, setStep] = useState(0);
  const [dest, setDest] = useState<Href | null>(null);
  const [animDone, setAnimDone] = useState(false);
  const [showCta, setShowCta] = useState(false);
  const went = useRef(false);

  const go = (href: Href) => {
    if (went.current) return;
    went.current = true;
    router.replace(href);
  };

  useEffect(() => {
    let alive = true;
    decideRoute().then((d) => {
      if (alive) setDest(d);
    });
    const statusTimer = setInterval(() => setStep((i) => Math.min(i + 1, STATUS.length - 1)), 800);
    const doneTimer = setTimeout(() => setAnimDone(true), 2400);
    const ctaTimer = setTimeout(() => setShowCta(true), 2900);
    return () => {
      alive = false;
      clearInterval(statusTimer);
      clearTimeout(doneTimer);
      clearTimeout(ctaTimer);
    };
  }, []);

  useEffect(() => {
    if (dest && animDone && !went.current) {
      went.current = true;
      router.replace(dest);
    }
  }, [dest, animDone]);

  // ---- progress bar + text + CTA
  const reduce = useReducedMotion();
  const bar = useSharedValue(reduce ? 1 : 0);
  const word = useSharedValue(reduce ? 1 : 0);
  const tag = useSharedValue(reduce ? 1 : 0);
  const cta = useSharedValue(0);

  useEffect(() => {
    if (reduce) return;
    bar.value = withDelay(400, withTiming(1, { duration: 2400, easing: Easing.bezier(0.4, 0, 0.2, 1) }));
    const rise = { duration: 600, easing: Easing.bezier(0.2, 0.7, 0.2, 1) };
    word.value = withDelay(1900, withTiming(1, rise));
    tag.value = withDelay(2150, withTiming(1, rise));
  }, [reduce, bar, word, tag]);

  useEffect(() => {
    if (showCta) cta.value = withTiming(1, { duration: 500 });
  }, [showCta, cta]);

  const barStyle = useAnimatedStyle(() => ({ width: `${bar.value * 100}%` }));
  const wordStyle = useAnimatedStyle(() => ({ opacity: word.value, transform: [{ translateY: (1 - word.value) * 12 }] }));
  const tagStyle = useAnimatedStyle(() => ({ opacity: tag.value, transform: [{ translateY: (1 - tag.value) * 12 }] }));
  const ctaStyle = useAnimatedStyle(() => ({ opacity: cta.value }));

  return (
    <View style={[styles.screen, { paddingTop: top, paddingBottom: bottom }]}>
      <View style={{ height: 8 }} />

      <View style={styles.center}>
        <HandiLogo reduce={reduce} />
        <View style={{ alignItems: 'center', gap: 8 }}>
          <Animated.View style={wordStyle}>
            <Txt v="logo" color={C.primary} accessibilityRole="header">
              Handi
            </Txt>
          </Animated.View>
          <Animated.View style={tagStyle}>
            <Txt v="bodyL" lh={23.2} color={C.muted} align="center">
              {'What should we cook today?\nAnswered in under 10 seconds.'}
            </Txt>
          </Animated.View>
        </View>
      </View>

      <View style={styles.bottom}>
        <View style={styles.track} accessibilityRole="progressbar" accessibilityLabel="Loading">
          <Animated.View style={[styles.fill, barStyle]} />
        </View>
        <Txt v="helper" color={C.muted} style={{ minHeight: 18 }} accessibilityLiveRegion="polite">
          {STATUS[step]}
        </Txt>
        <Animated.View style={ctaStyle} pointerEvents={showCta ? 'auto' : 'none'}>
          <Button
            label="Tap to continue"
            variant="pill"
            fill={false}
            onPress={() => go(dest ?? (useAuth.getState().token ? '/today' : '/welcome'))}
          />
        </Animated.View>
      </View>
    </View>
  );
}

// ---------------------------------------------------------------- Animated clay-pot logo

const DOTS = [70, 86, 100, 114, 130];
const DOT_Y = [152, 157, 158, 157, 152];
const STEAM = ['M80 54 C 70 42 90 34 80 20', 'M100 50 C 90 36 110 28 100 10', 'M120 54 C 110 42 130 34 120 20'];

function HandiLogo({ reduce }: { reduce: boolean }) {
  const done = reduce ? 1 : 0;
  const draw = useSharedValue(done); // body stroke
  const fill = useSharedValue(done); // body fill
  const neck = useSharedValue(done);
  const band = useSharedValue(done);
  const dots = useSharedValue(done);
  const lid = useSharedValue(done);
  const knob = useSharedValue(done);
  const bob = useSharedValue(0);

  useEffect(() => {
    if (reduce) return;
    draw.value = withDelay(100, withTiming(1, { duration: 1000, easing: Easing.bezier(0.6, 0, 0.3, 1) }));
    fill.value = withDelay(900, withTiming(1, { duration: 500 }));
    neck.value = withDelay(1000, withTiming(1, { duration: 400 }));
    band.value = withDelay(1250, withTiming(1, { duration: 600 }));
    lid.value = withDelay(1350, withTiming(1, { duration: 700, easing: Easing.linear }));
    dots.value = withDelay(1700, withTiming(1, { duration: 300 }));
    knob.value = withDelay(1850, withTiming(1, { duration: 450, easing: Easing.linear }));
    const half = { duration: 1600, easing: Easing.inOut(Easing.ease) };
    bob.value = withDelay(2400, withRepeat(withSequence(withTiming(1, half), withTiming(0, half)), -1, false));
  }, [reduce, draw, fill, neck, band, dots, lid, knob, bob]);

  const bodyProps = useAnimatedProps(() => ({ strokeDashoffset: 420 * (1 - draw.value), fillOpacity: fill.value }));
  const neckProps = useAnimatedProps(() => ({ fillOpacity: neck.value }));
  const bandProps = useAnimatedProps(() => ({ strokeDashoffset: 120 * (1 - band.value) }));
  const dotsProps = useAnimatedProps(() => ({ opacity: dots.value }));

  const potStyle = useAnimatedStyle(() => ({ transform: [{ translateY: -3 * bob.value }] }));
  const shadowStyle = useAnimatedStyle(() => ({
    opacity: interpolate(bob.value, [0, 1], [0.12, 0.08]),
    transform: [{ scaleX: interpolate(bob.value, [0, 1], [1, 0.92]) }],
  }));
  const lidStyle = useAnimatedStyle(() => ({
    opacity: interpolate(lid.value, [0, 0.6, 1], [0, 1, 1]),
    transform: [{ translateY: interpolate(lid.value, [0, 0.6, 0.8, 1], [-34, 4, -2, 0]) }],
  }));
  const knobStyle = useAnimatedStyle(() => ({
    opacity: interpolate(knob.value, [0, 0.7, 1], [0, 1, 1]),
    transform: [{ scale: interpolate(knob.value, [0, 0.7, 1], [0.2, 1.25, 1]) }],
  }));

  return (
    <View style={styles.logo} accessible accessibilityRole="image" accessibilityLabel="Handi logo: a clay cooking pot with steam rising">
      {/* shadow */}
      <Animated.View style={[styles.layer, { left: 42, top: 171, width: 116, height: 14 }, shadowStyle]}>
        <Svg width={116} height={14} viewBox="42 171 116 14">
          <Ellipse cx={100} cy={178} rx={58} ry={7} fill={C.ink} />
        </Svg>
      </Animated.View>

      {/* steam */}
      {STEAM.map((d, i) => (
        <Steam key={d} d={d} delay={2000 + i * 450} reduce={reduce} />
      ))}

      <Animated.View style={[StyleSheet.absoluteFill, potStyle]}>
        <Svg width={200} height={200} viewBox="0 0 200 200" style={StyleSheet.absoluteFill}>
          <AnimatedPath
            d="M64 96 C 38 108 34 152 70 170 C 86 177 114 177 130 170 C 166 152 162 108 136 96 Z"
            fill="#C8643B"
            stroke="#8E3F20"
            strokeWidth={3}
            strokeLinejoin="round"
            strokeDasharray={[420, 420]}
            animatedProps={bodyProps}
          />
          <AnimatedPath d="M44 132 C 78 144 122 144 156 132" fill="none" stroke={C.accent} strokeWidth={6} strokeLinecap="round" strokeDasharray={[120, 120]} animatedProps={bandProps} />
          <AnimatedG fill="#FBE3B0" animatedProps={dotsProps}>
            {DOTS.map((cx, i) => (
              <Circle key={cx} cx={cx} cy={DOT_Y[i]} r={3} />
            ))}
          </AnimatedG>
          <AnimatedRect x={60} y={86} width={80} height={14} rx={7} fill="#A9472A" stroke="#8E3F20" strokeWidth={3} animatedProps={neckProps} />
        </Svg>

        {/* lid */}
        <Animated.View style={[StyleSheet.absoluteFill, lidStyle]}>
          <Svg width={200} height={200} viewBox="0 0 200 200">
            <Path d="M56 86 C 62 64 138 64 144 86 Z" fill={C.primary} stroke={C.primaryPressed} strokeWidth={3} strokeLinejoin="round" />
            <Path d="M70 78 C 84 70 116 70 130 78" fill="none" stroke={C.primaryLine} strokeWidth={3} strokeLinecap="round" />
          </Svg>
        </Animated.View>

        {/* knob */}
        <Animated.View style={[styles.layer, { left: 91, top: 55, width: 18, height: 18 }, knobStyle]}>
          <Svg width={18} height={18} viewBox="91 55 18 18">
            <Circle cx={100} cy={64} r={7} fill={C.accent} stroke={C.accentDeep} strokeWidth={2.5} />
          </Svg>
        </Animated.View>
      </Animated.View>
    </View>
  );
}

function Steam({ d, delay, reduce }: { d: string; delay: number; reduce: boolean }) {
  const p = useSharedValue(0);
  useEffect(() => {
    if (reduce) return;
    p.value = withDelay(delay, withRepeat(withTiming(1, { duration: 2600, easing: Easing.inOut(Easing.ease) }), -1, false));
  }, [reduce, delay, p]);
  return <SteamPath d={d} p={p} reduce={reduce} />;
}

function SteamPath({ d, p, reduce }: { d: string; p: SharedValue<number>; reduce: boolean }) {
  const style = useAnimatedStyle(() => ({
    opacity: reduce ? 0.5 : interpolate(p.value, [0, 0.25, 0.7, 1], [0, 0.75, 0.45, 0]),
    transform: [{ translateY: reduce ? 0 : interpolate(p.value, [0, 1], [8, -14]) }],
  }));
  const props = useAnimatedProps(() => ({ strokeDashoffset: reduce ? 0 : 64 - 128 * p.value }));
  return (
    <Animated.View style={[StyleSheet.absoluteFill, style]}>
      <Svg width={200} height={200} viewBox="0 0 200 200">
        <AnimatedPath d={d} fill="none" stroke="#B9B1A4" strokeWidth={4.5} strokeLinecap="round" strokeDasharray={[64, 64]} animatedProps={props} />
      </Svg>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.white, alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 32 },
  center: { alignItems: 'center', gap: 18 },
  bottom: { alignItems: 'center', gap: 16, alignSelf: 'stretch' },
  track: { width: 140, height: 4, borderRadius: 99, backgroundColor: C.divider, overflow: 'hidden' },
  fill: { height: 4, borderRadius: 99, backgroundColor: C.primary },
  logo: { width: 200, height: 200 },
  layer: { position: 'absolute' },
});
