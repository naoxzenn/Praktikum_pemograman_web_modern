<template>
  <div>
    <header class="site-header">
      <nav class="container navbar" aria-label="Navigasi utama">
        <NuxtLink to="/" class="brand">Portal Mahasiswa</NuxtLink>
        <div class="nav-links">
          <NuxtLink to="/">Beranda</NuxtLink>
          <NuxtLink to="/layanan">Daftar Layanan</NuxtLink>
          <NuxtLink to="/pengajuan">Pengajuan</NuxtLink>
          <NuxtLink to="/status">Lacak Status</NuxtLink>
          <NuxtLink to="/admin">Petugas ↗</NuxtLink>
        </div>
      </nav>
    </header>
    <NuxtPage />
    <footer class="site-footer">
      <div class="container">
        <strong>Portal Layanan Mahasiswa</strong>
        <p>Informasi dan layanan administrasi kampus, dalam satu tempat.</p>
      </div>
    </footer>
  </div>
</template>
