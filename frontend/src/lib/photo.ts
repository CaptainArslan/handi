import * as ImagePicker from 'expo-image-picker';

/** A photo ready to send as a multipart `photo` field. */
export interface PickedPhoto {
  uri: string;
  name: string;
  type: string;
  size: number | null;
}

export const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

export type PickResult = { ok: true; photo: PickedPhoto } | { ok: false; reason: 'canceled' | 'denied' | 'too-big' };

/** Open the camera or the gallery and return one compressed image (≤10 MB). */
export async function pickPhoto(source: 'camera' | 'library' = 'library'): Promise<PickResult> {
  const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.8 };
  let result: ImagePicker.ImagePickerResult;
  if (source === 'camera') {
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) return { ok: false, reason: 'denied' };
    result = await ImagePicker.launchCameraAsync(options);
  } else {
    result = await ImagePicker.launchImageLibraryAsync(options);
  }
  const asset = result.canceled ? null : result.assets[0];
  if (!asset) return { ok: false, reason: 'canceled' };
  if (asset.fileSize && asset.fileSize > MAX_PHOTO_BYTES) return { ok: false, reason: 'too-big' };

  const type = asset.mimeType ?? 'image/jpeg';
  const ext = type.split('/')[1]?.replace('jpeg', 'jpg') ?? 'jpg';
  const name = asset.fileName ?? `dish-photo.${ext}`;
  return { ok: true, photo: { uri: asset.uri, name, type, size: asset.fileSize ?? null } };
}
