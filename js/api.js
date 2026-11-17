// Pembungkus fetch() untuk menggantikan localStorage di frontend.
// Pastikan BASE sesuai lokasi folder proyek di htdocs.
const BASE = 'api';   // relatif terhadap index.html

async function api(path, method = 'GET', data) {
  const res = await fetch(`${BASE}/${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: data ? JSON.stringify(data) : undefined,
  });
  if (res.status === 401 && !path.startsWith('auth.php')) {   // sesi habis / belum login
    location.href = 'login.html';
    throw new Error('Silakan login terlebih dahulu');
  }
  const json = await res.json();
  if (!res.ok) throw new Error(json.error || 'Request gagal');
  return json;
}

const API = {
  auth: {
    me:       ()  => api('auth.php?action=me'),
    login:    d   => api('auth.php?action=login', 'POST', d),
    register: d   => api('auth.php?action=register', 'POST', d),
    logout:   ()  => api('auth.php?action=logout', 'POST'),
  },
  courses: {
    list:   ()        => api('courses.php'),
    create: d         => api('courses.php', 'POST', d),
    update: (id, d)   => api(`courses.php?id=${id}`, 'PUT', d),
    remove: id        => api(`courses.php?id=${id}`, 'DELETE'),
  },
  tasks: {
    list:     ()      => api('tasks.php'),
    upcoming: (n = 7) => api(`tasks.php?upcoming=${n}`),
    create:   d       => api('tasks.php', 'POST', d),
    update:   (id, d) => api(`tasks.php?id=${id}`, 'PUT', d),
    setStatus:(id, s) => api(`tasks.php?id=${id}`, 'PATCH', { status: s }),
    remove:   id      => api(`tasks.php?id=${id}`, 'DELETE'),
  },
  calendar: (from, to) => api(`sessions.php?from=${from}&to=${to}`),
  notes: {
    get:  sid          => api(`notes.php?session_id=${sid}`),
    save: (sid, isi)   => api('notes.php', 'POST', { session_id: sid, isi_catatan: isi }),
  },
};
