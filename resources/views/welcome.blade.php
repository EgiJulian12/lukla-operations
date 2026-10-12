<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Lukla Operations Engine menyatukan traceability, produksi, QC, dan payroll untuk fasilitas manufaktur multi-klien.">

        <title>Lukla Operations Engine | Kendali operasi manufaktur</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --ink: #0b0922;
                --paper: #f7f8fa;
                --paper-deep: #e9edf2;
                --line: #d7dbe4;
                --orange: #22b8ad;
                --orange-dark: #087a78;
                --sage: #7c8da5;
            }

            html {
                scroll-behavior: smooth;
            }

            body {
                background: var(--paper);
                color: var(--ink);
                font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            }

            .display {
                font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
                letter-spacing: -0.055em;
                font-weight: 600;
            }

            .hairline {
                border-color: var(--line);
            }

            .paper-grid {
                background-image: linear-gradient(to right, rgba(255, 255, 255, 0.09) 1px, transparent 1px);
                background-size: 25% 100%;
            }

            .focus-ring:focus-visible {
                outline: 3px solid var(--orange);
                outline-offset: 4px;
            }

            .flow-line {
                background: linear-gradient(90deg, var(--orange) 0%, var(--orange) 16.66%, var(--line) 16.66%, var(--line) 100%);
            }

            @media (max-width: 767px) {
                .paper-grid {
                    background-image: none;
                }
            }
        </style>
    </head>
    <body class="antialiased">
        <header class="border-b hairline">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10" aria-label="Navigasi utama">
                <a href="#top" class="focus-ring flex min-h-11 items-center gap-3" aria-label="Lukla Operations Engine, kembali ke atas">
                    <span class="grid h-9 w-9 place-items-center border-2 border-[var(--ink)] text-sm font-bold">L</span>
                    <span class="display text-base font-bold tracking-tight">LUKLA<span class="text-[var(--orange)]">/</span>OPS</span>
                </a>

                <button id="menu-toggle" type="button" class="focus-ring flex min-h-11 items-center gap-2 border border-[var(--ink)] px-4 text-sm font-semibold md:hidden" aria-expanded="false" aria-controls="mobile-menu">
                    <span>Menu</span>
                    <span aria-hidden="true">+</span>
                </button>

                <div class="hidden items-center gap-8 text-sm font-semibold md:flex">
                    <a class="focus-ring py-3 hover:text-[var(--orange-dark)]" href="#system">Sistem</a>
                    <a class="focus-ring py-3 hover:text-[var(--orange-dark)]" href="#flow">Alur kerja</a>
                    <a class="focus-ring py-3 hover:text-[var(--orange-dark)]" href="#access">Akses</a>
                    <a class="focus-ring border border-[var(--orange-dark)] bg-[var(--orange-dark)] px-5 py-3 text-white transition hover:bg-[var(--ink)] hover:text-white" href="{{ url('/admin/login') }}">Masuk admin</a>
                    <a class="focus-ring border border-[var(--orange)] px-5 py-3 text-[var(--orange-dark)] transition hover:bg-[var(--orange)] hover:text-[var(--ink)]" href="{{ url('/portal/login') }}">Portal klien</a>
                </div>
            </nav>

            <div id="mobile-menu" class="hidden border-t hairline md:hidden">
                <div class="mx-auto flex max-w-7xl flex-col px-5 py-3 sm:px-8">
                    <a class="focus-ring border-b hairline py-4 font-semibold" href="#system">Sistem</a>
                    <a class="focus-ring border-b hairline py-4 font-semibold" href="#flow">Alur kerja</a>
                    <a class="focus-ring border-b hairline py-4 font-semibold" href="#access">Akses</a>
                    <a class="focus-ring border-t hairline py-4 font-semibold text-[var(--orange-dark)]" href="{{ url('/admin/login') }}">Masuk admin</a>
                    <a class="focus-ring py-4 font-semibold text-[var(--orange-dark)]" href="{{ url('/portal/login') }}">Portal klien</a>
                </div>
            </div>
        </header>

        <main id="top">
            <section class="paper-grid border-b hairline bg-[var(--ink)] text-[var(--paper)]">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:items-end lg:gap-16 lg:px-10 lg:py-28">
                    <div>
                        <p class="mb-8 flex items-center gap-3 text-xs font-bold uppercase tracking-[0.2em] text-[var(--orange)]">
                            <span class="h-2 w-2 bg-[var(--orange)]" aria-hidden="true"></span>
                            Lukla Manufacturing x Snøhund
                        </p>
                        <h1 class="display max-w-4xl text-5xl font-semibold leading-[0.95] sm:text-7xl lg:text-[6.7rem]">
                            Operasi yang bisa ditelusuri.
                        </h1>
                        <p class="mt-8 max-w-xl text-lg leading-8 text-[color:var(--paper)]/75 sm:text-xl">
                            Lukla Operations Engine menjaga kepemilikan material, menghubungkan sample sampai barang jadi, dan membuat payroll tetap akurat saat kapasitas pabrik berubah.
                        </p>
                        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                            <a href="#flow" class="focus-ring inline-flex min-h-12 items-center justify-center bg-[var(--orange)] px-6 font-bold text-white transition hover:bg-[var(--orange-dark)]">
                                Lihat alur operasional
                            </a>
                            <a href="#system" class="focus-ring inline-flex min-h-12 items-center justify-center border border-[var(--paper)] px-6 font-bold transition hover:bg-[var(--paper)] hover:text-[var(--ink)]">
                                Kenali sistemnya
                            </a>
                        </div>
                    </div>

                    <div class="relative lg:pb-8">
                        <div class="border-2 border-[var(--paper)] bg-[#e7ebef] p-3 text-[var(--ink)] shadow-[12px_12px_0_var(--orange)]">
                            <div class="flex items-center justify-between border-b border-[var(--ink)] px-2 pb-3 text-[10px] font-bold uppercase tracking-[0.18em]">
                                <span>Operations / Overview</span>
                                <span class="text-[var(--orange-dark)]">Live workspace</span>
                            </div>
                            <div class="grid gap-3 p-2 pt-5 sm:grid-cols-[0.8fr_1.2fr]">
                                <div class="border border-[var(--ink)] bg-[var(--paper)] p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--ink)]/55">Status produksi</p>
                                    <p class="display mt-4 text-5xl font-semibold">30<span class="text-2xl">m</span></p>
                                    <p class="mt-1 text-xs text-[var(--ink)]/65">interval output terverifikasi</p>
                                    <div class="mt-8 flex items-end gap-1" aria-label="Ilustrasi kecepatan output">
                                        <span class="h-8 w-2 bg-[var(--sage)]"></span><span class="h-12 w-2 bg-[var(--sage)]"></span><span class="h-16 w-2 bg-[var(--orange)]"></span><span class="h-10 w-2 bg-[var(--sage)]"></span><span class="h-20 w-2 bg-[var(--ink)]"></span><span class="h-14 w-2 bg-[var(--orange)]"></span>
                                    </div>
                                </div>
                                <div class="border border-[var(--ink)] bg-[var(--ink)] p-4 text-[var(--paper)]">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--paper)]/60">Traceability check</p>
                                        <span class="h-2 w-2 bg-[#b9d38d]" aria-label="Status aktif"></span>
                                    </div>
                                    <div class="mt-7 space-y-4 text-sm">
                                        <div class="flex items-start justify-between gap-4 border-b border-white/20 pb-3"><span>Client ID</span><strong class="font-medium text-[#b9d38d]">terikat</strong></div>
                                        <div class="flex items-start justify-between gap-4 border-b border-white/20 pb-3"><span>Order ID</span><strong class="font-medium text-[#b9d38d]">terikat</strong></div>
                                        <div class="flex items-start justify-between gap-4"><span>Issue bahan</span><strong class="font-medium text-[var(--orange)]">divalidasi</strong></div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-2 pb-2 text-center text-[10px] font-bold uppercase tracking-[0.12em]">
                                <div class="border border-[var(--ink)] bg-[var(--paper)] py-3">Sample</div>
                                <div class="border border-[var(--ink)] bg-[var(--paper)] py-3">Line</div>
                                <div class="border border-[var(--ink)] bg-[var(--paper)] py-3">QC</div>
                            </div>
                        </div>
                        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-[var(--paper)]/70">Satu sumber kebenaran untuk lantai produksi.</p>
                    </div>
                </div>
            </section>

            <section id="system" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28 lg:px-10">
                <div class="grid gap-12 lg:grid-cols-[0.36fr_0.64fr] lg:gap-24">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-[var(--orange-dark)]">01 / Mengapa sistem ini</p>
                        <h2 class="display mt-6 text-4xl font-semibold leading-none sm:text-5xl">Masalahnya terjadi di sela-sela proses.</h2>
                    </div>
                    <div class="divide-y hairline border-y">
                        <article class="grid gap-6 py-7 sm:grid-cols-[5rem_1fr] sm:gap-8">
                            <span class="display text-4xl font-semibold text-[var(--orange)]">01</span>
                            <div><h3 class="text-xl font-bold">Stok lintas klien harus tetap terpisah.</h3><p class="mt-3 max-w-2xl leading-7 text-[var(--ink)]/70">Setiap material, WIP, dan barang jadi membawa Client ID serta Order ID. Sistem menolak issue ketika keduanya tidak cocok dengan tujuan.</p></div>
                        </article>
                        <article class="grid gap-6 py-7 sm:grid-cols-[5rem_1fr] sm:gap-8">
                            <span class="display text-4xl font-semibold text-[var(--orange)]">02</span>
                            <div><h3 class="text-xl font-bold">Payroll mengikuti kapasitas nyata.</h3><p class="mt-3 max-w-2xl leading-7 text-[var(--ink)]/70">Output per 30 menit yang sudah diverifikasi menjadi dasar upah borongan, uang makan, bonus QC, dan overhead per unit.</p></div>
                        </article>
                        <article class="grid gap-6 py-7 sm:grid-cols-[5rem_1fr] sm:gap-8">
                            <span class="display text-4xl font-semibold text-[var(--orange)]">03</span>
                            <div><h3 class="text-xl font-bold">Revisi sample tidak boleh hilang.</h3><p class="mt-3 max-w-2xl leading-7 text-[var(--ink)]/70">Riwayat spesifikasi, time study, pemakaian bahan, dan approval ikut terbawa ketika sample berubah menjadi order massal.</p></div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="flow" class="border-y hairline bg-[var(--ink)] text-[var(--paper)]">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28 lg:px-10">
                    <div class="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#f19a73]">02 / Alur operasional</p>
                            <h2 class="display mt-6 text-4xl font-semibold leading-none sm:text-6xl">Dari sample ke payroll, tanpa putus konteks.</h2>
                        </div>
                        <p class="max-w-xs leading-7 text-[var(--paper)]/65">Enam fase, satu jejak audit, dan kewenangan yang jelas untuk setiap peran.</p>
                    </div>

                    <div class="mt-16 overflow-x-auto pb-4">
                        <div class="min-w-[720px]">
                            <div class="flow-line h-1"></div>
                            <div class="grid grid-cols-6 gap-4 pt-6">
                                <div><span class="text-xs text-[var(--paper)]/45">01</span><h3 class="mt-4 font-bold">Sample</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">PO, titipan, time study</p></div>
                                <div><span class="text-xs text-[var(--paper)]/45">02</span><h3 class="mt-4 font-bold">Approval</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">CMT, Semi-CMT, Full Package</p></div>
                                <div><span class="text-xs text-[var(--paper)]/45">03</span><h3 class="mt-4 font-bold">Gudang</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">Receiving dan cutting</p></div>
                                <div><span class="text-xs text-[var(--paper)]/45">04</span><h3 class="mt-4 font-bold">Line</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">Alokasi dinamis dan output</p></div>
                                <div><span class="text-xs text-[var(--paper)]/45">05</span><h3 class="mt-4 font-bold">QC</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">Pass, repair, scrap</p></div>
                                <div><span class="text-xs text-[var(--paper)]/45">06</span><h3 class="mt-4 font-bold">Payroll</h3><p class="mt-2 text-sm leading-6 text-[var(--paper)]/60">Borongan dan rekap biaya</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-14 grid gap-4 sm:grid-cols-3">
                        <div class="border border-white/20 p-6"><p class="display text-5xl font-semibold">±70</p><p class="mt-3 text-sm text-[var(--paper)]/60">mesin jahit aktif, dengan kapasitas yang bisa berkembang</p></div>
                        <div class="border border-white/20 p-6"><p class="display text-5xl font-semibold">8</p><p class="mt-3 text-sm text-[var(--paper)]/60">peran operasional dengan batas akses yang jelas</p></div>
                        <div class="border border-[var(--orange)] bg-[var(--orange)] p-6 text-white"><p class="display text-5xl font-semibold">1</p><p class="mt-3 text-sm text-white/80">rekam operasi yang menghubungkan material, output, QC, dan payroll</p></div>
                    </div>
                </div>
            </section>

            <section id="access" class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28 lg:px-10">
                <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-start lg:gap-24">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-[var(--orange-dark)]">03 / Akses yang bertanggung jawab</p>
                        <h2 class="display mt-6 text-4xl font-semibold leading-none sm:text-6xl">Semua orang melihat pekerjaan yang tepat.</h2>
                        <p class="mt-6 max-w-md leading-7 text-[var(--ink)]/70">Owner, sample team, gudang, mandor, penjahit, QC, finance, dan klien maklon bekerja di sistem yang sama tanpa membuka data yang bukan miliknya.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="border hairline p-5"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--orange-dark)]">Internal</p><p class="mt-4 font-bold">Owner / Executive</p><p class="mt-2 text-sm leading-6 text-[var(--ink)]/65">Analytics lintas klien dan audit history.</p></div>
                        <div class="border hairline p-5"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--orange-dark)]">Floor</p><p class="mt-4 font-bold">Produksi / QC</p><p class="mt-2 text-sm leading-6 text-[var(--ink)]/65">Line, output, assembly, dan hasil inspeksi.</p></div>
                        <div class="border hairline p-5"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--orange-dark)]">Finance</p><p class="mt-4 font-bold">Payroll / Cost</p><p class="mt-2 text-sm leading-6 text-[var(--ink)]/65">Rate card, periode mingguan, dan overhead.</p></div>
                        <div class="border hairline bg-[var(--paper-deep)] p-5"><p class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--orange-dark)]">Client-scoped</p><p class="mt-4 font-bold">Maklon / B2B Portal</p><p class="mt-2 text-sm leading-6 text-[var(--ink)]/65">Progres order dan stok titipan milik sendiri.</p></div>
                    </div>
                </div>
            </section>

            <section id="contact" class="border-t hairline bg-[var(--orange)] text-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-20 sm:px-8 sm:py-24 lg:flex-row lg:items-end lg:justify-between lg:px-10">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/70">Lukla Operations Engine</p>
                        <h2 class="display mt-6 text-5xl font-semibold leading-[0.95] sm:text-7xl">Bangun kendali operasi dari sumber datanya.</h2>
                    </div>
                    <a href="{{ url('/admin') }}" class="focus-ring inline-flex min-h-12 shrink-0 items-center justify-center bg-[var(--ink)] px-6 font-bold text-white transition hover:bg-white hover:text-[var(--ink)]">Tinjau flow operasional</a>
                </div>
            </section>
        </main>

        <footer class="bg-[var(--ink)] text-[var(--paper)]">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                <p class="font-semibold">LUKLA/OPS</p>
                <p class="text-[var(--paper)]/55">Ciwidey production facility · Operational system brief</p>
            </div>
        </footer>

        <script>
            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');

            menuToggle?.addEventListener('click', () => {
                const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
                menuToggle.setAttribute('aria-expanded', String(!isOpen));
                mobileMenu.classList.toggle('hidden', isOpen);
                menuToggle.querySelector('span:last-child').textContent = isOpen ? '+' : '×';
            });

            mobileMenu?.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    menuToggle.setAttribute('aria-expanded', 'false');
                    mobileMenu.classList.add('hidden');
                    menuToggle.querySelector('span:last-child').textContent = '+';
                });
            });
        </script>
    </body>
</html>
