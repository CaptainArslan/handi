import { Text, type TextProps, type TextStyle } from 'react-native';

import { C, T } from '@/theme/tokens';

export type TxtVariant = keyof typeof T;

export interface TxtProps extends TextProps {
  /** A text role from the design's type scale (tokens.text). Default `body`. */
  v?: TxtVariant;
  color?: string;
  /** Override the Poppins weight: 400 | 500 | 600 | 700. */
  weight?: 400 | 500 | 600 | 700;
  size?: number;
  lh?: number;
  align?: TextStyle['textAlign'];
}

const POPPINS = { 400: 'Poppins_400Regular', 500: 'Poppins_500Medium', 600: 'Poppins_600SemiBold', 700: 'Poppins_700Bold' } as const;

/** All text in the app — Poppins (700 for display roles and headings). */
export function Txt({ v = 'body', color = C.ink, weight, size, lh, align, style, ...rest }: TxtProps) {
  const base = T[v] as TextStyle;
  return (
    <Text
      allowFontScaling
      maxFontSizeMultiplier={1.4}
      {...rest}
      style={[
        base,
        { color },
        weight ? { fontFamily: POPPINS[weight] } : null,
        size ? { fontSize: size } : null,
        lh ? { lineHeight: lh } : null,
        align ? { textAlign: align } : null,
        style,
      ]}
    />
  );
}

/** Font family for a Poppins weight (for TextInput styles). */
export const figtree = (w: 400 | 500 | 600 | 700) => POPPINS[w];
export const DISPLAY = 'Poppins_700Bold';
