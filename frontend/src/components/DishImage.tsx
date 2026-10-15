import { Image } from 'expo-image';
import { View, type StyleProp, type ViewStyle } from 'react-native';

import { mediaUrl } from '@/lib/config';
import { C, tokens } from '@/theme/tokens';

/** The design's illustrations, bundled so pictures work offline. */
export const ART = {
  lentils: require('../../assets/dishes/lentils.webp'),
  bhindi: require('../../assets/dishes/bhindi.webp'),
  karahi: require('../../assets/dishes/karahi.webp'),
  biryani: require('../../assets/dishes/biryani.webp'),
  qeema: require('../../assets/dishes/qeema.webp'),
  fish: require('../../assets/dishes/fish.webp'),
  paratha: require('../../assets/dishes/paratha.webp'),
  karhi: require('../../assets/dishes/karhi.webp'),
  aloogobi: require('../../assets/dishes/aloogobi.webp'),
} as const;

export type ArtKey = keyof typeof ART;

/** Default art per category (custom dishes without a photo). */
export const CATEGORY_ART: Record<string, ArtKey> = {
  vegetables: 'bhindi',
  lentils: 'lentils',
  chicken: 'karahi',
  beef: 'qeema',
  mutton: 'karahi',
  fish: 'fish',
  rice: 'biryani',
  breakfast: 'paratha',
  snacks: 'karhi',
};

export function artSource(imageUrl: string | null | undefined, imageKey: string | null | undefined) {
  // Server art (…/images/dishes/{key}.png) → use the bundled copy; real photos → load the URL.
  const url = mediaUrl(imageUrl);
  if (url && !url.includes('/images/dishes/')) return { uri: url };
  if (imageKey && imageKey in ART) return ART[imageKey as ArtKey];
  return url ? { uri: url } : ART.karhi;
}

export const artBg = (imageKey: string | null | undefined, fallback?: string | null) =>
  fallback ?? (imageKey && imageKey in tokens.dishBg ? tokens.dishBg[imageKey as keyof typeof tokens.dishBg] : C.sand);

/** Dish picture over its tint, `cover` with the design's slight zoom. */
export function DishImage({
  imageUrl,
  imageKey,
  bg,
  scale = 1.12,
  radius = 0,
  style,
  alt,
  source,
}: {
  imageUrl?: string | null;
  imageKey?: string | null;
  bg?: string | null;
  scale?: number;
  radius?: number;
  style?: StyleProp<ViewStyle>;
  alt?: string;
  /** Local uri (picked photo) — wins over url/key. */
  source?: string | null;
}) {
  return (
    <View style={[{ overflow: 'hidden', borderRadius: radius, backgroundColor: artBg(imageKey, bg) }, style]}>
      <Image
        source={source ? { uri: source } : artSource(imageUrl, imageKey)}
        contentFit="cover"
        transition={150}
        accessibilityLabel={alt}
        style={{ width: '100%', height: '100%', transform: [{ scale }] }}
      />
    </View>
  );
}
