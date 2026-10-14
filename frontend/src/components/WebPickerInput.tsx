import { createElement } from 'react';
import { Platform } from 'react-native';

/**
 * Web only: an invisible native <input type="date|time"> laid over a field, so clicking the
 * design's field opens the browser's own picker (the native picker library renders nothing on web).
 */
export function WebPickerInput({
  type,
  value,
  onChange,
  min,
  max,
  label,
}: {
  type: 'date' | 'time';
  value: string;
  onChange: (v: string) => void;
  min?: string;
  max?: string;
  label: string;
}) {
  if (Platform.OS !== 'web') return null;
  return createElement('input', {
    type,
    value,
    min,
    max,
    step: type === 'time' ? 300 : undefined,
    'aria-label': label,
    onChange: (e: { target: { value: string } }) => e.target.value && onChange(e.target.value),
    style: { position: 'absolute', inset: 0, width: '100%', height: '100%', opacity: 0, cursor: 'pointer', border: 0, padding: 0, margin: 0 },
  });
}
