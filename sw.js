// sw.js - Service Worker para funcionalidade offline
// IMPORTANTE: Mude este número quando fizer alterações no código
const CACHE_VERSION = 'recognicao-v4'; // Incrementar versão para forçar atualização
const CACHE_NAME = CACHE_VERSION;

// Arquivos essenciais - usar caminhos relativos corretos
const urlsToCache = [
  '/', // Raiz
  '/index.php',
  '/formulario.php',
  '/config.php',
  '/offline.html',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
  'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
  'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css'
];

// URLs que sempre devem buscar do servidor quando online
const ALWAYS_NETWORK = [
  'api.php',
  'gerar_pdf.php',
  'ver_fotos.php'
];

// Instalar Service Worker
self.addEventListener('install', event => {
  console.log('Service Worker: Instalando versão', CACHE_VERSION);
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Tentando cachear arquivos...');
        
        // Cachear arquivos um por um para melhor controle de erro
        return Promise.all(
          urlsToCache.map(url => {
            return cache.add(url).catch(err => {
              console.error('Erro ao cachear:', url, err);
              // Não falhar toda a instalação se um arquivo falhar
              return Promise.resolve();
            });
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Instalação completa');
        return self.skipWaiting();
      })
      .catch(err => {
        console.error('Service Worker: Erro na instalação', err);
      })
  );
});

// Ativar Service Worker
self.addEventListener('activate', event => {
  console.log('Service Worker: Ativando versão', CACHE_VERSION);
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Removendo cache antigo', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Ativação completa');
      return self.clients.claim();
    })
  );
});

// Interceptar requisições
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Ignorar requisições que não são GET
  if (request.method !== 'GET') {
    return;
  }
  
  // Verificar se deve sempre buscar da rede
  const shouldAlwaysNetwork = ALWAYS_NETWORK.some(file => url.pathname.includes(file));
  if (shouldAlwaysNetwork) {
    return;
  }
  
  // Normalizar URL para formulário com parâmetros
  let cacheKey = request.url;
  if (url.pathname.includes('formulario.php') && url.search) {
    // Para formulário com ID, usar versão sem parâmetros como chave de cache
    cacheKey = url.origin + url.pathname;
  }
  
  // Para arquivos PHP, usar estratégia Network First
  if (request.url.includes('.php')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              // Cachear com a chave normalizada
              cache.put(cacheKey, responseToCache);
              console.log('Service Worker: Cacheado', cacheKey);
            });
          }
          return response;
        })
        .catch(() => {
          // Offline - tentar cache
          console.log('Service Worker: Offline, buscando no cache', cacheKey);
          
          return caches.match(cacheKey).then(response => {
            if (response) {
              console.log('Service Worker: Encontrado no cache');
              return response;
            }
            
            // Tentar variações da URL
            return caches.match(url.pathname).then(response2 => {
              if (response2) {
                return response2;
              }
              
              // Tentar sem parâmetros
              return caches.match(url.origin + url.pathname).then(response3 => {
                if (response3) {
                  return response3;
                }
                
                // Se for formulário, tentar retornar o formulário base
                if (url.pathname.includes('formulario.php')) {
                  return caches.match('/formulario.php').then(formResponse => {
                    if (formResponse) {
                      return formResponse;
                    }
                    
                    // Última tentativa - página offline
                    return caches.match('/offline.html').then(offlineResponse => {
                      if (offlineResponse) {
                        return offlineResponse;
                      }
                      
                      // Retornar erro HTML
                      return new Response(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Erro - Offline</title>
                            <style>
                                body { 
                                    font-family: Arial; 
                                    text-align: center; 
                                    padding: 50px;
                                    background: #f5f5f5;
                                }
                                .error-box {
                                    background: white;
                                    padding: 30px;
                                    border-radius: 10px;
                                    max-width: 500px;
                                    margin: 0 auto;
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                                }
                                .error-icon { font-size: 48px; margin-bottom: 20px; }
                                button {
                                    background: #1a237e;
                                    color: white;
                                    border: none;
                                    padding: 10px 20px;
                                    border-radius: 5px;
                                    cursor: pointer;
                                    margin: 5px;
                                }
                            </style>
                        </head>
                        <body>
                            <div class="error-box">
                                <div class="error-icon">⚠️</div>
                                <h2>Página não disponível offline</h2>
                                <p>O formulário não foi carregado corretamente para uso offline.</p>
                                <p>Por favor, conecte-se à internet e recarregue a página.</p>
                                <button onclick="location.href='/'">Voltar ao Início</button>
                                <button onclick="location.reload()">Tentar Novamente</button>
                            </div>
                        </body>
                        </html>
                      `, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                        status: 404
                      });
                    });
                  });
                }
                
                return new Response('Página não encontrada no cache', {
                  status: 404,
                  statusText: 'Not Found'
                });
              });
            });
          });
        })
    );
    return;
  }
  
  // Para outros arquivos (CSS, JS, etc), usar Cache First
  event.respondWith(
    caches.match(request)
      .then(response => {
        if (response) {
          return response;
        }
        
        return fetch(request).then(response => {
          if (!response || response.status !== 200) {
            return response;
          }
          
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, responseToCache);
          });
          
          return response;
        });
      })
  );
});

// Sincronização em background
self.addEventListener('sync', event => {
  if (event.tag === 'sync-recognicoes') {
    console.log('Service Worker: Sincronizando dados...');
    event.waitUntil(sincronizarDados());
  }
});

async function sincronizarDados() {
  console.log('Service Worker: Sincronização iniciada');
  
  const clients = await self.clients.matchAll();
  clients.forEach(client => {
    client.postMessage({
      type: 'sync-status',
      message: 'Sincronizando dados...'
    });
  });
}

// Mensagem para limpar cache manualmente
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'CLEAR_ALL_CACHE') {
    event.waitUntil(
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            console.log('Limpando cache:', cacheName);
            return caches.delete(cacheName);
          })
        );
      })
    );
  }
});