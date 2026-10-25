import Svg, { Circle, Path, Rect } from 'react-native-svg';

import { C } from '@/theme/tokens';

/**
 * The design's icon set — exact paths from the Handi design (viewBox 24, stroke icons,
 * round caps and joins). Do not swap for a font icon set.
 */
const ICONS = {
  chevronLeft: [<Path key="a" d="M15 18l-6-6 6-6" />],
  chevronRight: [<Path key="a" d="M9 18l6-6-6-6" />],
  close: [<Path key="a" d="M18 6L6 18M6 6l12 12" />],
  check: [<Path key="a" d="M20 6L9 17l-5-5" />],
  plus: [<Path key="a" d="M12 5v14M5 12h14" />],
  minus: [<Path key="a" d="M5 12h14" />],
  arrowRight: [<Path key="a" d="M5 12h14M13 6l6 6-6 6" />],
  home: [<Path key="a" d="M3 10.5L12 3l9 7.5V21h-6v-6H9v6H3z" />],
  bowl: [<Path key="a" d="M3 11h18a9 9 0 0 1-18 0z" />, <Path key="b" d="M12 3v4M8 5v2M16 5v2" />],
  calendar: [<Rect key="a" x={3} y={5} width={18} height={16} rx={2} />, <Path key="b" d="M3 10h18M8 3v4M16 3v4" />],
  sliders: [
    <Path key="a" d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12" />,
    <Circle key="b" cx={16} cy={6} r={2} />,
    <Circle key="c" cx={10} cy={12} r={2} />,
    <Circle key="d" cx={18} cy={18} r={2} />,
  ],
  bell: [<Path key="a" d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4" />],
  search: [<Circle key="a" cx={11} cy={11} r={7} />, <Path key="b" d="M20 20l-3.5-3.5" />],
  mail: [<Rect key="a" x={3} y={5} width={18} height={14} rx={2} />, <Path key="b" d="M3 7l9 6 9-6" />],
  lock: [<Rect key="a" x={4} y={10} width={16} height={11} rx={2} />, <Path key="b" d="M8 10V7a4 4 0 0 1 8 0v3" />],
  lockKey: [
    <Rect key="a" x={4} y={10} width={16} height={11} rx={2} />,
    <Path key="b" d="M8 10V7a4 4 0 0 1 8 0v3" />,
    <Path key="c" d="M12 14.5v2.5" />,
  ],
  eye: [<Path key="a" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />, <Circle key="b" cx={12} cy={12} r={3} />],
  eyeOff: [
    <Path key="a" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />,
    <Circle key="b" cx={12} cy={12} r={3} />,
    <Path key="c" d="M3 3l18 18" />,
  ],
  pin: [<Path key="a" d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z" />, <Circle key="b" cx={12} cy={9} r={2.5} />],
  crosshair: [<Circle key="a" cx={12} cy={12} r={3} />, <Path key="b" d="M12 2v3M12 19v3M2 12h3M19 12h3" />],
  sun: [
    <Circle key="a" cx={12} cy={12} r={4} />,
    <Path key="b" d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />,
  ],
  sunSimple: [<Circle key="a" cx={12} cy={12} r={4} />, <Path key="b" d="M12 2v2M12 20v2M2 12h2M20 12h2" />],
  rain: [<Path key="a" d="M7 15a5 5 0 1 1 1-9.9A6 6 0 0 1 19 8a4 4 0 0 1-1 7.9M8 19l-1 2M12 19l-1 2M16 19l-1 2" />],
  snow: [<Path key="a" d="M12 2v20M4.9 7l14.2 10M4.9 17L19.1 7M9 4l3 2 3-2M9 20l3-2 3 2" />],
  cloud: [<Path key="a" d="M8 7a4 4 0 0 1 7.5 1.5M17.5 19H8a5 5 0 1 1 .8-9.9A5.5 5.5 0 0 1 19 11.5 3.8 3.8 0 0 1 17.5 19z" />],
  shuffle: [<Path key="a" d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5" />],
  camera: [<Path key="a" d="M4 8h3l2-3h6l2 3h3v12H4z" />, <Circle key="b" cx={12} cy={13} r={3.5} />],
  trash: [<Path key="a" d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />],
  pencil: [<Path key="a" d="M4 20h4L19 9l-4-4L4 16z" />, <Path key="b" d="M14 6l4 4" />],
  reset: [<Path key="a" d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5" />],
  alert: [<Circle key="a" cx={12} cy={12} r={9} />, <Path key="b" d="M12 7v6M12 16.5h.01" />],
  info: [<Circle key="a" cx={12} cy={12} r={9} />, <Path key="b" d="M12 11v5M12 7.5h.01" />],
  clock: [<Circle key="a" cx={12} cy={12} r={9} />, <Path key="b" d="M12 7v5l3 2" />],
  sync: [<Path key="a" d="M21 12a9 9 0 0 1-15.5 6.2M3 12a9 9 0 0 1 15.5-6.2M21 4v5h-5M3 20v-5h5" />],
  send: [<Path key="a" d="M22 2L11 13M22 2l-7 20-4-9-9-4z" />],
  shieldCheck: [<Path key="a" d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z" />, <Path key="b" d="M9 12l2 2 4-4" />],
  wifi: [<Path key="a" d="M5 12.6a10 10 0 0 1 14 0M8.5 16.1a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 20 0M12 20h.01" />],
  copy: [<Rect key="a" x={9} y={9} width={12} height={12} rx={2} />, <Path key="b" d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" />],
  sparkles: [<Path key="a" d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z" />, <Path key="b" d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" />],
  external: [<Path key="a" d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />],
  paperclip: [<Path key="a" d="M21 11.5l-8.6 8.6a5.5 5.5 0 0 1-7.8-7.8l8.6-8.6a3.7 3.7 0 0 1 5.2 5.2l-8.6 8.6a1.8 1.8 0 0 1-2.6-2.6l7.9-7.9" />],
  file: [<Path key="a" d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z" />, <Path key="b" d="M14 3v5h5M9 13h6M9 17h4" />],
  help: [<Circle key="a" cx={12} cy={12} r={9} />, <Path key="b" d="M9.5 9.5a2.5 2.5 0 0 1 4.9.7c0 1.7-2.4 2.3-2.4 3.8M12 17h.01" />],
  wifiOff: [
    <Path key="a" d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.8M19 12.9a10 10 0 0 0-2.3-1.6M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M12 20h.01" />,
  ],
} as const;

export type IconName = keyof typeof ICONS;

export interface IconProps {
  name: IconName;
  size?: number;
  color?: string;
  strokeWidth?: number;
}

export function Icon({ name, size = 20, color = C.ink, strokeWidth = 2 }: IconProps) {
  return (
    <Svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke={color}
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round">
      {ICONS[name]}
    </Svg>
  );
}

export const WEATHER_ICON: Record<string, IconName> = { hot: 'sun', mild: 'cloud', cold: 'snow', rainy: 'rain', any: 'sunSimple' };
