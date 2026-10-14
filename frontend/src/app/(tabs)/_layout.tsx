import { Redirect, Tabs } from 'expo-router';

import { FloatNav } from '@/components/FloatNav';
import { useAuth } from '@/store/auth';
import { C } from '@/theme/tokens';

/** Tabs 3 · 4 · 5 · 7 with the floating nav bar (8.3). */
export default function TabsLayout() {
  const token = useAuth((s) => s.token);
  if (!token) return <Redirect href="/welcome" />;

  return (
    <Tabs
      tabBar={(props) => <FloatNav {...props} />}
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: C.bg }, animation: 'fade' }}>
      <Tabs.Screen name="today" />
      <Tabs.Screen name="dishes" />
      <Tabs.Screen name="history" />
      <Tabs.Screen name="settings" />
    </Tabs>
  );
}
