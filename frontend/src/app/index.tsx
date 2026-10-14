import { router, type Href } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { useVideoPlayer, VideoView } from 'expo-video';
import Svg, { Defs, LinearGradient, Rect, Stop } from 'react-native-svg';
import Animated, { Easing, useAnimatedStyle, useReducedMotion, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';

import { isNetworkError, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk } from '@/api/queries';
import { downloadLibrary, hasLibrary } from '@/components/AuthKit';
import { useBottom } from '@/components/Layout';
import { Button } from '@/components/ui/Button';
import { Txt } from '@/components/ui/Txt';
import { queryClient } from '@/lib/query-client';
import { useAuth } from '@/store/auth';
import { useNet } from '@/store/net';
import { C } from '@/theme/tokens';

const STATUS = ['Warming up the kitchen…', 'Checking your session…', 'Syncing the dish library…', 'Ready'];

/** Where the app should open: Today (session), No connection (first launch offline) or Welcome. */
async function decideRoute(): Promise<Href> {
  const online = await useNet
    .getState()
    .recheck()
    .catch(() => true);

  if (useAuth.getState().token) {
    if (!online) return '/today'; // offline mode with the saved session
    try {
      const me = await queryClient.fetchQuery({
        queryKey: qk.me,
        queryFn: endpoints.me,
        staleTime: 0,
      });
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
    const doneTimer = setTimeout(() => setAnimDone(true), 3200);
    const ctaTimer = setTimeout(() => setShowCta(true), 3700);
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
  const cta = useSharedValue(0);

  useEffect(() => {
    if (reduce) return;
    bar.value = withDelay(300, withTiming(1, { duration: 2900, easing: Easing.bezier(0.4, 0, 0.2, 1) }));
  }, [reduce, bar]);

  useEffect(() => {
    if (showCta) cta.value = withTiming(1, { duration: 500 });
  }, [showCta, cta]);

  const barStyle = useAnimatedStyle(() => ({ width: `${bar.value * 100}%` }));
  const ctaStyle = useAnimatedStyle(() => ({ opacity: cta.value }));

  return (
    <View style={[styles.screen, { paddingBottom: bottom }]}>
      {/* Logo sits dead centre — the same spot and size as the native splash image, so the hand-off is invisible. */}
      <View style={styles.center} pointerEvents="none">
        <HandiLogoVideo reduce={reduce} />
      </View>

      <View style={styles.bottom}>
        <View style={styles.track} accessibilityRole="progressbar" accessibilityLabel="Loading">
          <Animated.View style={[styles.fill, barStyle]} />
        </View>
        <Txt v="helper" color={C.muted} style={{ minHeight: 18 }} accessibilityLiveRegion="polite">
          {STATUS[step]}
        </Txt>
        <Animated.View style={ctaStyle} pointerEvents={showCta ? 'auto' : 'none'}>
          <Button label="Tap to continue" variant="pill" fill={false} onPress={() => go(dest ?? (useAuth.getState().token ? '/today' : '/welcome'))} />
        </Animated.View>
      </View>
    </View>
  );
}

// ---------------------------------------------------------------- Handi logo video

/** Splash background = the brand video's paper colour, so video and screen read as one surface. */
const SPLASH_BG = '#FAF9F0';
const LOGO_W = 220;
const LOGO_H = Math.round((LOGO_W * 660) / 460);
const VIDEO = require('../../assets/splash/handi-splash.mp4');
const POSTER = require('../../assets/splash/handi-splash-poster.png');

/** The animated Handi logo (pot, steam, “Handi”, “Aaj kya pakaen?”), muted and looping. */
function HandiLogoVideo({ reduce }: { reduce: boolean }) {
  const player = useVideoPlayer(reduce ? null : VIDEO, (p) => {
    p.loop = true;
    p.muted = true;
    p.play();
  });

  return (
    <View style={styles.logo} accessible accessibilityRole="image" accessibilityLabel="Handi — Aaj kya pakaen?">
      {/* First frame underneath, so there is no flash before the video starts. */}
      <Image source={POSTER} style={StyleSheet.absoluteFill} contentFit="contain" />
      {reduce ? null : (
        <View style={StyleSheet.absoluteFill}>
          <VideoView
            player={player}
            style={{ width: LOGO_W, height: LOGO_H }}
            contentFit="contain"
            nativeControls={false}
            allowsPictureInPicture={false}
            surfaceType="textureView"
          />
        </View>
      )}
      <EdgeFade />
    </View>
  );
}

/** Soft fade from the screen colour at the logo box edges: hides any 1-step colour difference a phone's video decoder adds. */
function EdgeFade() {
  const f = 14;
  return (
    <Svg width={LOGO_W} height={LOGO_H} style={StyleSheet.absoluteFill} pointerEvents="none">
      <Defs>
        <LinearGradient id="t" x1="0" y1="0" x2="0" y2="1">
          <Stop offset="0" stopColor={SPLASH_BG} stopOpacity={1} />
          <Stop offset="1" stopColor={SPLASH_BG} stopOpacity={0} />
        </LinearGradient>
        <LinearGradient id="b" x1="0" y1="1" x2="0" y2="0">
          <Stop offset="0" stopColor={SPLASH_BG} stopOpacity={1} />
          <Stop offset="1" stopColor={SPLASH_BG} stopOpacity={0} />
        </LinearGradient>
        <LinearGradient id="l" x1="0" y1="0" x2="1" y2="0">
          <Stop offset="0" stopColor={SPLASH_BG} stopOpacity={1} />
          <Stop offset="1" stopColor={SPLASH_BG} stopOpacity={0} />
        </LinearGradient>
        <LinearGradient id="r" x1="1" y1="0" x2="0" y2="0">
          <Stop offset="0" stopColor={SPLASH_BG} stopOpacity={1} />
          <Stop offset="1" stopColor={SPLASH_BG} stopOpacity={0} />
        </LinearGradient>
      </Defs>
      <Rect x={0} y={0} width={LOGO_W} height={f} fill="url(#t)" />
      <Rect x={0} y={LOGO_H - f} width={LOGO_W} height={f} fill="url(#b)" />
      <Rect x={0} y={0} width={f} height={LOGO_H} fill="url(#l)" />
      <Rect x={LOGO_W - f} y={0} width={f} height={LOGO_H} fill="url(#r)" />
    </Svg>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: SPLASH_BG,
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingHorizontal: 32,
  },
  center: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    alignItems: 'center',
    justifyContent: 'center',
  },
  bottom: { alignItems: 'center', gap: 16, alignSelf: 'stretch' },
  track: {
    width: 140,
    height: 4,
    borderRadius: 99,
    backgroundColor: 'rgba(110, 72, 40, 0.12)',
    overflow: 'hidden',
  },
  fill: { height: 4, borderRadius: 99, backgroundColor: C.primary },
  logo: { width: LOGO_W, height: LOGO_H },
});
