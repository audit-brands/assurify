// Service Worker for Lobsters PWA
const CACHE_NAME = 'lobsters-v1.0.0';
const API_CACHE_NAME = 'lobsters-api-v1.0.0';
const STATIC_CACHE_NAME = 'lobsters-static-v1.0.0';

// Files to cache immediately
const STATIC_ASSETS = [
  '/',
  '/assets/application.css',
  '/assets/application.js',
  '/manifest.json',
  '/offline.html'
];

// API endpoints to cache
const API_ENDPOINTS = [
  '/api/v1/stories',
  '/api/v2/stories'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE_NAME).then(cache => {
        console.log('Caching static assets...');
        return cache.addAll(STATIC_ASSETS.filter(url => url !== '/offline.html'));
      }),
      caches.open(CACHE_NAME).then(cache => {
        console.log('Creating offline page cache...');
        return cache.add('/offline.html');
      })
    ]).then(() => {
      console.log('Service Worker installed successfully');
      // Force activation of new service worker
      return self.skipWaiting();
    }).catch(error => {
      console.error('Service Worker installation failed:', error);
    })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME && 
                cacheName !== API_CACHE_NAME && 
                cacheName !== STATIC_CACHE_NAME) {
              console.log('Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),
      // Take control of all pages
      self.clients.claim()
    ]).then(() => {
      console.log('Service Worker activated successfully');
    })
  );
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip chrome-extension and other non-http requests
  if (!url.protocol.startsWith('http')) {
    return;
  }
  
  // Handle different types of requests
  if (url.pathname.startsWith('/api/')) {
    // API requests - Network first, cache fallback
    event.respondWith(handleApiRequest(request));
  } else if (isStaticAsset(url.pathname)) {
    // Static assets - Cache first, network fallback
    event.respondWith(handleStaticAsset(request));
  } else {
    // HTML pages - Network first, cache fallback with offline page
    event.respondWith(handlePageRequest(request));
  }
});

// Handle API requests with network-first strategy
async function handleApiRequest(request) {
  const cache = await caches.open(API_CACHE_NAME);
  
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful responses
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('Network failed for API request, trying cache:', request.url);
    
    // Fallback to cache
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline API response
    return new Response(
      JSON.stringify({
        success: false,
        message: 'You are currently offline. Please check your connection.',
        offline: true,
        timestamp: new Date().toISOString()
      }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        }
      }
    );
  }
}

// Handle static assets with cache-first strategy
async function handleStaticAsset(request) {
  const cache = await caches.open(STATIC_CACHE_NAME);
  
  // Try cache first
  const cachedResponse = await cache.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    // Fallback to network
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache the response
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('Failed to fetch static asset:', request.url);
    // Return empty response for missing static assets
    return new Response('', { status: 404 });
  }
}

// Handle page requests with network-first strategy
async function handlePageRequest(request) {
  const cache = await caches.open(CACHE_NAME);
  
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful HTML responses
      if (networkResponse.headers.get('content-type')?.includes('text/html')) {
        cache.put(request, networkResponse.clone());
      }
    }
    
    return networkResponse;
  } catch (error) {
    console.log('Network failed for page request, trying cache:', request.url);
    
    // Try cached version
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fallback to offline page for HTML requests
    if (request.headers.get('accept')?.includes('text/html')) {
      const offlineResponse = await cache.match('/offline.html');
      if (offlineResponse) {
        return offlineResponse;
      }
    }
    
    // Return generic offline response
    return new Response('You are currently offline.', {
      status: 503,
      statusText: 'Service Unavailable'
    });
  }
}

// Helper function to identify static assets
function isStaticAsset(pathname) {
  const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
  return staticExtensions.some(ext => pathname.endsWith(ext)) || pathname.startsWith('/assets/');
}

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('Background sync triggered:', event.tag);
  
  switch (event.tag) {
    case 'background-sync-actions':
      event.waitUntil(syncOfflineActions());
      break;
    case 'background-sync-stories':
      event.waitUntil(syncStories());
      break;
    case 'background-sync-comments':
      event.waitUntil(syncComments());
      break;
    default:
      console.log('Unknown sync tag:', event.tag);
  }
});

// Sync all pending offline actions
async function syncOfflineActions() {
  try {
    console.log('Starting offline action sync...');
    
    // Get pending actions from IndexedDB
    const pendingActions = await getStoredActions();
    
    if (pendingActions.length === 0) {
      console.log('No pending actions to sync');
      return;
    }
    
    console.log(`Syncing ${pendingActions.length} pending actions`);
    
    for (const action of pendingActions) {
      try {
        const success = await syncAction(action);
        if (success) {
          await removeStoredAction(action.id);
          console.log('Synced action:', action.type, action.id);
        } else {
          await incrementActionAttempts(action.id);
          console.log('Failed to sync action:', action.type, action.id);
        }
      } catch (error) {
        console.error('Error syncing action:', action, error);
        await incrementActionAttempts(action.id);
      }
    }
    
    // Notify main thread of sync completion
    await notifyClients('sync-completed', { 
      type: 'actions',
      synced: pendingActions.length 
    });
    
  } catch (error) {
    console.error('Background sync failed:', error);
  }
}

// Sync individual action
async function syncAction(action) {
  const endpoint = '/api/v1/sync/queue';
  
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': await getStoredAuthToken()
      },
      body: JSON.stringify({
        type: action.type,
        data: action.data
      })
    });
    
    return response.ok;
  } catch (error) {
    console.error('Failed to sync action:', error);
    return false;
  }
}

// Sync cached stories
async function syncStories() {
  try {
    console.log('Syncing stories...');
    
    const response = await fetch('/api/v1/stories?limit=50', {
      headers: {
        'Authorization': await getStoredAuthToken()
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success && data.data) {
        await storeData('cached-stories', data.data);
        await notifyClients('sync-completed', { 
          type: 'stories',
          count: data.data.length 
        });
        console.log(`Synced ${data.data.length} stories`);
      }
    }
  } catch (error) {
    console.error('Failed to sync stories:', error);
  }
}

// Sync cached comments
async function syncComments() {
  try {
    console.log('Syncing comments...');
    
    const cachedStories = await getData('cached-stories') || [];
    
    for (const story of cachedStories.slice(0, 10)) { // Limit to first 10 stories
      try {
        const response = await fetch(`/api/v1/stories/${story.id}/comments`, {
          headers: {
            'Authorization': await getStoredAuthToken()
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.success && data.data) {
            await storeData(`story-comments-${story.id}`, data.data);
          }
        }
      } catch (error) {
        console.error(`Failed to sync comments for story ${story.id}:`, error);
      }
    }
    
    await notifyClients('sync-completed', { type: 'comments' });
  } catch (error) {
    console.error('Failed to sync comments:', error);
  }
}

// IndexedDB operations for offline storage
async function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('LobstersOffline', 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Create object stores
      if (!db.objectStoreNames.contains('actions')) {
        const actionStore = db.createObjectStore('actions', { keyPath: 'id' });
        actionStore.createIndex('type', 'type', { unique: false });
        actionStore.createIndex('timestamp', 'timestamp', { unique: false });
      }
      
      if (!db.objectStoreNames.contains('cache')) {
        db.createObjectStore('cache', { keyPath: 'key' });
      }
      
      if (!db.objectStoreNames.contains('auth')) {
        db.createObjectStore('auth', { keyPath: 'type' });
      }
    };
  });
}

// Store action for offline sync
async function storeAction(action) {
  const db = await openDB();
  const transaction = db.transaction(['actions'], 'readwrite');
  const store = transaction.objectStore('actions');
  
  const actionWithId = {
    id: generateActionId(),
    timestamp: Date.now(),
    attempts: 0,
    ...action
  };
  
  await store.add(actionWithId);
  return actionWithId.id;
}

// Get all stored actions
async function getStoredActions() {
  const db = await openDB();
  const transaction = db.transaction(['actions'], 'readonly');
  const store = transaction.objectStore('actions');
  
  return new Promise((resolve, reject) => {
    const request = store.getAll();
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });
}

// Remove synced action
async function removeStoredAction(actionId) {
  const db = await openDB();
  const transaction = db.transaction(['actions'], 'readwrite');
  const store = transaction.objectStore('actions');
  await store.delete(actionId);
}

// Increment action attempt count
async function incrementActionAttempts(actionId) {
  const db = await openDB();
  const transaction = db.transaction(['actions'], 'readwrite');
  const store = transaction.objectStore('actions');
  
  const action = await store.get(actionId);
  if (action) {
    action.attempts = (action.attempts || 0) + 1;
    action.lastAttempt = Date.now();
    
    // Remove after 3 failed attempts
    if (action.attempts >= 3) {
      await store.delete(actionId);
      console.log('Removed failed action after 3 attempts:', actionId);
    } else {
      await store.put(action);
    }
  }
}

// Store data in cache
async function storeData(key, data) {
  const db = await openDB();
  const transaction = db.transaction(['cache'], 'readwrite');
  const store = transaction.objectStore('cache');
  
  await store.put({
    key: key,
    data: data,
    timestamp: Date.now()
  });
}

// Get cached data
async function getData(key) {
  const db = await openDB();
  const transaction = db.transaction(['cache'], 'readonly');
  const store = transaction.objectStore('cache');
  
  return new Promise((resolve, reject) => {
    const request = store.get(key);
    request.onsuccess = () => {
      const result = request.result;
      if (result && !isExpired(result.timestamp)) {
        resolve(result.data);
      } else {
        resolve(null);
      }
    };
    request.onerror = () => reject(request.error);
  });
}

// Check if cached data is expired (4 hours TTL)
function isExpired(timestamp) {
  const TTL = 4 * 60 * 60 * 1000; // 4 hours
  return Date.now() - timestamp > TTL;
}

// Store authentication token
async function storeAuthToken(token) {
  const db = await openDB();
  const transaction = db.transaction(['auth'], 'readwrite');
  const store = transaction.objectStore('auth');
  
  await store.put({
    type: 'jwt',
    token: token,
    timestamp: Date.now()
  });
}

// Get stored authentication token
async function getStoredAuthToken() {
  const db = await openDB();
  const transaction = db.transaction(['auth'], 'readonly');
  const store = transaction.objectStore('auth');
  
  return new Promise((resolve, reject) => {
    const request = store.get('jwt');
    request.onsuccess = () => {
      const result = request.result;
      if (result && !isExpired(result.timestamp)) {
        resolve(`Bearer ${result.token}`);
      } else {
        resolve('');
      }
    };
    request.onerror = () => reject(request.error);
  });
}

// Generate unique action ID
function generateActionId() {
  return 'action_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Notify all clients of sync events
async function notifyClients(type, data) {
  const clients = await self.clients.matchAll({ includeUncontrolled: true });
  
  for (const client of clients) {
    client.postMessage({
      type: type,
      data: data,
      timestamp: Date.now()
    });
  }
}

// Push notification handling
self.addEventListener('push', event => {
  console.log('Push notification received:', event);
  
  let notificationData = {
    title: 'Lobsters',
    body: 'You have a new notification',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    tag: 'lobsters-notification',
    requireInteraction: false,
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/assets/icons/view-icon.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/assets/icons/dismiss-icon.png'
      }
    ]
  };
  
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = { ...notificationData, ...data };
    } catch (error) {
      console.error('Error parsing push notification data:', error);
    }
  }
  
  event.waitUntil(
    self.registration.showNotification(notificationData.title, notificationData)
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  const action = event.action;
  const notificationData = event.notification.data || {};
  
  if (action === 'dismiss') {
    return;
  }
  
  // Default action or 'view' action
  const urlToOpen = notificationData.url || '/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      // Check if there's already a window/tab open with the target URL
      for (const client of clientList) {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      
      // Open new window/tab
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// Handle message events from the main thread
self.addEventListener('message', event => {
  console.log('Service Worker received message:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_NAME });
  }
});

// Periodically clean up old cache entries
setInterval(() => {
  console.log('Performing periodic cache cleanup...');
  cleanupOldCacheEntries();
}, 24 * 60 * 60 * 1000); // Once per day

async function cleanupOldCacheEntries() {
  try {
    const cache = await caches.open(API_CACHE_NAME);
    const requests = await cache.keys();
    const now = Date.now();
    const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
    
    for (const request of requests) {
      const response = await cache.match(request);
      if (response) {
        const dateHeader = response.headers.get('date');
        if (dateHeader) {
          const responseDate = new Date(dateHeader).getTime();
          if (now - responseDate > maxAge) {
            await cache.delete(request);
            console.log('Deleted old cache entry:', request.url);
          }
        }
      }
    }
  } catch (error) {
    console.error('Cache cleanup failed:', error);
  }
}