import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';

/** A photo ready to send as a multipart `photo` field. */
export interface PickedPhoto {
  uri: string;
  name: string;
  type: string;
  size: number | null;
}

export const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

/**
 * Upload size: dish photos are shown at most ~400 pt wide (≈1200 px on a 3× phone), so 1600 px on
 * the long side keeps them sharp everywhere while a 12 MP camera shot (4–8 MB) drops to ~300–600 KB.
 * JPEG 0.82 is visually lossless for food photos.
 */
const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.82;

export type PickResult = { ok: true; photo: PickedPhoto } | { ok: false; reason: 'canceled' | 'denied' | 'too-big' };

/** Open the camera or the gallery and return one image, resized and compressed for upload. */
export async function pickPhoto(source: 'camera' | 'library' = 'library'): Promise<PickResult> {
  // Full quality from the picker: we compress once, below (compressing twice loses more detail).
  const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 1 };
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

  const photo = await compressPhoto(asset);
  if (photo.size && photo.size > MAX_PHOTO_BYTES) return { ok: false, reason: 'too-big' };
  return { ok: true, photo };
}

/** Resize to ≤ MAX_EDGE on the long side and re-encode as JPEG. Falls back to the original on any error. */
export async function compressPhoto(asset: Pick<ImagePicker.ImagePickerAsset, 'uri' | 'width' | 'height' | 'mimeType' | 'fileName' | 'fileSize'>): Promise<PickedPhoto> {
  const original: PickedPhoto = {
    uri: asset.uri,
    type: asset.mimeType ?? 'image/jpeg',
    name: asset.fileName ?? 'dish-photo.jpg',
    size: asset.fileSize ?? null,
  };
  try {
    const ctx = ImageManipulator.manipulate(asset.uri);
    const { width, height } = asset;
    if (width && height && Math.max(width, height) > MAX_EDGE) {
      ctx.resize(width >= height ? { width: MAX_EDGE } : { height: MAX_EDGE });
    }
    const image = await ctx.renderAsync();
    const saved = await image.saveAsync({ format: SaveFormat.JPEG, compress: JPEG_QUALITY });
    const size = await fileSize(saved.uri);
    // A small, already-optimised image can come out bigger after re-encoding: keep whichever is smaller.
    if (size !== null && original.size !== null && size >= original.size) return original;
    const base = (asset.fileName ?? 'dish-photo').replace(/\.[^.]+$/, '');
    return { uri: saved.uri, type: 'image/jpeg', name: `${base}.jpg`, size };
  } catch {
    return original;
  }
}

async function fileSize(uri: string): Promise<number | null> {
  try {
    const blob = await (await fetch(uri)).blob();
    return blob.size;
  } catch {
    return null;
  }
}
