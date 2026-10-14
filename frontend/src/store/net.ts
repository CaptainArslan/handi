import NetInfo from '@react-native-community/netinfo';
import { create } from 'zustand';

interface NetState {
  online: boolean;
  recheck: () => Promise<boolean>;
}

/** Connectivity from NetInfo. `online` is optimistic until NetInfo answers. */
export const useNet = create<NetState>((set) => ({
  online: true,
  recheck: async () => {
    const s = await NetInfo.fetch();
    const online = s.isConnected !== false && s.isInternetReachable !== false;
    set({ online });
    return online;
  },
}));

NetInfo.addEventListener((s) => {
  const online = s.isConnected !== false && s.isInternetReachable !== false;
  if (useNet.getState().online !== online) useNet.setState({ online });
});

export const isOnline = () => useNet.getState().online;
