import { router, type Href } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { Image } from 'expo-image';
import { StatusBar } from 'expo-status-bar';
import { useVideoPlayer, VideoView } from 'expo-video';
import { useEffect, useRef, useState } from 'react';
import { StyleSheet, useWindowDimensions, View } from 'react-native';
import Animated, {
  Easing,
  interpolate,
  runOnJS,
  useAnimatedProps,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withDelay,
  withTiming,
} from 'react-native-reanimated';
import Svg, { Circle, Defs, RadialGradient, Rect, Stop } from 'react-native-svg';

import { isNetworkError, status } from '@/api/client';
import { endpoints } from '@/api/endpoints';
import { qk } from '@/api/queries';
import { downloadLibrary, hasLibrary } from '@/components/AuthKit';
import { queryClient } from '@/lib/query-client';
import { LOADER_BG } from '@/lib/splash';
import { useAuth } from '@/store/auth';
import { useNet } from '@/store/net';
import { C } from '@/theme/tokens';

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

/** The loader stays at least this long so the video and the ring can play before the app opens. */
const MIN_SPLASH_MS = 3400;

/** White intro: the full Handi logo (same as the native splash) grows a little, then fades into the loader. */
const LOGO_FULL = require('../../assets/splash/handi-logo-full.png');
const LOGO_RATIO = 357 / 588; // width / height of the logo artwork
// Android 12+ draws the native splash logo at 55% of a 288dp icon box ≈ 158dp tall; start there so the
// hand-over is seamless, then grow to the bigger size.
const INTRO_START_H = 158;
const INTRO_END_H = 236;
const INTRO_HOLD_MS = 1150; // when the white intro starts fading into the dark loader

/** The video's own paper colour: the circle is filled with it so the video has no visible edges. */
const VIDEO_PAPER = '#FAF9F0';

const D = 236; // circle diameter
const RING_GAP = 10; // space between the circle and the loader ring
const RING_W = 5; // loader ring thickness
const RING_SIZE = D + (RING_GAP + RING_W) * 2;
const RING_R = (RING_SIZE - RING_W) / 2;
const RING_LEN = 2 * Math.PI * RING_R;

const VIDEO = require('../../assets/splash/handi-splash.mp4');
const POSTER = require('../../assets/splash/handi-splash-poster.png');
const VIDEO_H = Math.round(D * 0.9);
const VIDEO_W = Math.round((VIDEO_H * 460) / 660);

const AnimatedCircle = Animated.createAnimatedComponent(Circle);

/**
 * Loader screen (after the white native splash with the logo).
 *
 * The video plays in a cream circle on a dark background with an amber ring filling around it while
 * the app checks the session and the dish library. At the end:
 *   1. the ring completes,
 *   2. a plain cream disc (same size, drawn ABOVE the video) fades in — the video itself is never
 *      scaled or faded, which is what used to flash a black video surface on Android,
 *   3. the disc grows until it covers the screen, and the next screen (also cream) opens under it.
 */
export default function Splash() {
  const reduce = useReducedMotion();
  const { width, height } = useWindowDimensions();
  const [dest, setDest] = useState<Href | null>(null);
  const [minDone, setMinDone] = useState(false);
  const [introOver, setIntroOver] = useState(false); // status bar: dark icons on white, light on the loader
  const went = useRef(false);
  const splashHidden = useRef(false);

  const progress = useSharedValue(0); // ring 0…1
  const cover = useSharedValue(0); // disc opacity (0 → 1 hides the video circle)
  const grow = useSharedValue(0); // disc scale progress (0 = circle, 1 = whole screen)
  const introGrow = useSharedValue(0); // white intro logo: 0 = native size, 1 = big
  const introFade = useSharedValue(1); // white intro layer opacity

  const player = useVideoPlayer(reduce ? null : VIDEO, (p) => {
    p.loop = true;
    p.muted = true;
    p.play();
  });

  // First frame is on screen → cross-fade the native splash away (no blank screen in between).
  const onFirstLayout = () => {
    if (splashHidden.current) return;
    splashHidden.current = true;
    SplashScreen.hideAsync().catch(() => undefined);
  };

  useEffect(() => {
    if (reduce) {
      introFade.set(withTiming(0, { duration: 200 }));
    } else {
      introGrow.set(withDelay(120, withTiming(1, { duration: 650, easing: Easing.out(Easing.cubic) })));
      introFade.set(withDelay(INTRO_HOLD_MS, withTiming(0, { duration: 380, easing: Easing.inOut(Easing.quad) })));
    }
    progress.set(
      withDelay(reduce ? 0 : INTRO_HOLD_MS, withTiming(0.86, { duration: reduce ? 400 : MIN_SPLASH_MS - INTRO_HOLD_MS, easing: Easing.out(Easing.cubic) })),
    );
    let alive = true;
    decideRoute().then((d) => {
      if (alive) setDest(d);
    });
    const timer = setTimeout(() => setMinDone(true), reduce ? 500 : MIN_SPLASH_MS);
    const introTimer = setTimeout(() => setIntroOver(true), reduce ? 0 : INTRO_HOLD_MS + 150);
    return () => {
      alive = false;
      clearTimeout(timer);
      clearTimeout(introTimer);
    };
  }, [reduce, progress, introGrow, introFade]);

  useEffect(() => {
    if (!dest || !minDone || went.current) return;
    went.current = true;
    const open = () => router.replace(dest);
    if (reduce) {
      open();
      return;
    }
    progress.set(withTiming(1, { duration: 300, easing: Easing.out(Easing.quad) }));
    cover.set(withDelay(320, withTiming(1, { duration: 220, easing: Easing.out(Easing.quad) })));
    grow.set(
      withDelay(
        480,
        withTiming(1, { duration: 560, easing: Easing.in(Easing.cubic) }, (done) => {
          if (done) runOnJS(open)();
        }),
      ),
    );
  }, [dest, minDone, reduce, progress, cover, grow]);

  // Scale that makes the disc cover the screen from the centre (corner to corner, plus a margin).
  const fill = Math.sqrt(width * width + height * height) / D + 0.2;

  const introStyle = useAnimatedStyle(() => ({ opacity: introFade.get() }));
  const introLogoStyle = useAnimatedStyle(() => {
    const h = interpolate(introGrow.get(), [0, 1], [INTRO_START_H, INTRO_END_H]);
    return { height: h, width: h * LOGO_RATIO };
  });
  const ringProps = useAnimatedProps(() => ({ strokeDashoffset: RING_LEN * (1 - progress.get()) }));
  const ringStyle = useAnimatedStyle(() => ({ opacity: 1 - cover.get() }));
  const discStyle = useAnimatedStyle(() => ({
    opacity: cover.get(),
    transform: [{ scale: interpolate(grow.get(), [0, 1], [1, fill]) }],
  }));

  return (
    <View style={styles.screen} onLayout={onFirstLayout} accessible accessibilityRole="image" accessibilityLabel="Handi is loading">
      <StatusBar style={introOver ? 'light' : 'dark'} />
      <Glow width={width} height={height} />

      <Animated.View style={[styles.ring, ringStyle]} pointerEvents="none">
        <Svg width={RING_SIZE} height={RING_SIZE}>
          <Circle cx={RING_SIZE / 2} cy={RING_SIZE / 2} r={RING_R} stroke="rgba(255,255,255,0.14)" strokeWidth={RING_W} fill="none" />
          <AnimatedCircle
            cx={RING_SIZE / 2}
            cy={RING_SIZE / 2}
            r={RING_R}
            stroke={C.accent}
            strokeWidth={RING_W}
            strokeLinecap="round"
            fill="none"
            strokeDasharray={`${RING_LEN} ${RING_LEN}`}
            animatedProps={ringProps}
            transform={`rotate(-90 ${RING_SIZE / 2} ${RING_SIZE / 2})`}
          />
        </Svg>
      </Animated.View>

      {/* The video circle never moves or fades, so its video surface can't flash. */}
      <View style={styles.circle}>
        <View style={styles.video}>
          <Image source={POSTER} style={StyleSheet.absoluteFill} contentFit="contain" />
          {reduce ? null : (
            <VideoView
              player={player}
              style={StyleSheet.absoluteFill}
              contentFit="contain"
              nativeControls={false}
              allowsPictureInPicture={false}
              surfaceType="textureView"
            />
          )}
        </View>
      </View>

      {/* Plain cream disc on top: fades in over the video, then grows to fill the screen. */}
      <Animated.View style={[styles.disc, discStyle]} pointerEvents="none" />

      {/* White intro: continues the native splash (same logo, same place), grows, then fades away. */}
      <Animated.View style={[styles.intro, introStyle]} pointerEvents="none">
        <Animated.View style={introLogoStyle}>
          <Image source={LOGO_FULL} style={StyleSheet.absoluteFill} contentFit="contain" />
        </Animated.View>
      </Animated.View>
    </View>
  );
}

/** A soft warm glow behind the circle, so the dark background isn't flat. */
function Glow({ width, height }: { width: number; height: number }) {
  return (
    <Svg width={width} height={height} style={StyleSheet.absoluteFill} pointerEvents="none">
      <Defs>
        <RadialGradient id="glow" cx="50%" cy="50%" r="55%">
          <Stop offset="0" stopColor="#6B2F1A" stopOpacity={0.45} />
          <Stop offset="1" stopColor={LOADER_BG} stopOpacity={0} />
        </RadialGradient>
      </Defs>
      <Rect x={0} y={0} width={width} height={height} fill="url(#glow)" />
    </Svg>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: LOADER_BG, alignItems: 'center', justifyContent: 'center' },
  ring: { position: 'absolute', width: RING_SIZE, height: RING_SIZE },
  circle: {
    width: D,
    height: D,
    borderRadius: D / 2,
    overflow: 'hidden',
    backgroundColor: VIDEO_PAPER,
    alignItems: 'center',
    justifyContent: 'center',
  },
  video: { width: VIDEO_W, height: VIDEO_H },
  disc: { position: 'absolute', width: D, height: D, borderRadius: D / 2, backgroundColor: C.bg },
  intro: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center' },
});
