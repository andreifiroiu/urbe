import { Head, Link } from '@inertiajs/react';

export default function Landing() {
    return (
        <>
            <Head title="Ghes — Orașul îți dă ghes. Tu ce faci diseară?" />

            {/* ── HERO ─────────────────────────────────────────────────── */}
            <section
                className="min-h-screen flex flex-col"
                style={{ backgroundColor: '#0A1128' }}
            >
                {/* Navbar */}
                <nav className="flex items-center justify-between px-6 py-5 max-w-7xl mx-auto w-full">
                    <img
                        src="/images/logo-dark.png"
                        alt="Ghes"
                        className="h-10 w-auto"
                    />
                    <div className="flex items-center gap-3">
                        <Link
                            href="/login"
                            className="text-white/70 hover:text-white text-sm font-medium transition-colors px-4 py-2"
                        >
                            Intră în cont
                        </Link>
                        <Link
                            href="/register"
                            className="text-sm font-semibold px-5 py-2.5 rounded-full transition-all hover:opacity-90 active:scale-95"
                            style={{ backgroundColor: '#FF5733', color: '#fff' }}
                        >
                            Înregistrează-te
                        </Link>
                    </div>
                </nav>

                {/* Hero content */}
                <div className="flex-1 flex flex-col items-center justify-center text-center px-6 pb-24">
                    <p
                        className="text-sm font-semibold tracking-widest uppercase mb-6"
                        style={{ color: '#FF5733' }}
                    >
                        Descoperă ce se întâmplă în jurul tău într-un fel mai 'smart'
                    </p>
                    <h1
                        className="text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight mb-6 max-w-3xl"
                        style={{ fontFamily: 'Montserrat, sans-serif', color: '#F8F9FA' }}
                    >
                        Orașul îți dă ghes. Tu ce faci diseară?
                    </h1>
                    <p
                        className="text-lg sm:text-xl max-w-xl mb-10 leading-relaxed"
                        style={{ color: 'rgba(248,249,250,0.65)' }}
                    >
                        Ghes scanează orașul non-stop, clasifică evenimentele cu AI și îți
                        livrează doar ce se potrivește cu vibe-ul tău. Fără scroll infinit.
                        Fără zgomot.
                    </p>
                    <div className="flex flex-col sm:flex-row items-center gap-4">
                        <Link
                            href="/register"
                            className="text-base font-bold px-8 py-4 rounded-full shadow-lg transition-all hover:opacity-90 active:scale-95"
                            style={{ backgroundColor: '#FF5733', color: '#fff' }}
                        >
                            Vreau un ghes →
                        </Link>
                        <Link
                            href="/login"
                            className="text-base font-medium px-8 py-4 rounded-full border transition-all hover:bg-white/5"
                            style={{ borderColor: 'rgba(248,249,250,0.25)', color: '#F8F9FA' }}
                        >
                            Am deja cont
                        </Link>
                    </div>
                </div>

                {/* Wave separator */}
                <div className="w-full overflow-hidden leading-none" style={{ height: '64px' }}>
                    <svg viewBox="0 0 1440 64" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" className="w-full h-full">
                        <path d="M0,32 C360,64 1080,0 1440,32 L1440,64 L0,64 Z" fill="#F8F9FA" />
                    </svg>
                </div>
            </section>

            {/* ── FEATURES ─────────────────────────────────────────────── */}
            <section className="py-24 px-6" style={{ backgroundColor: '#F8F9FA' }}>
                <div className="max-w-5xl mx-auto">
                    <h2
                        className="text-3xl sm:text-4xl font-extrabold text-center mb-4"
                        style={{ fontFamily: 'Montserrat, sans-serif', color: '#0A1128' }}
                    >
                        De ce Ghes?
                    </h2>
                    <p className="text-center text-gray-500 mb-16 text-base">
                        Nu e un calendar de evenimente. E prietenul tău spontan care știe orașul pe de rost.
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        {[
                            {
                                icon: '🔍',
                                title: 'Secretele orașului',
                                desc: 'Ghes descoperă evenimente de pe zeci de surse — inclusiv cele pe care nu le-ai găsi altfel. "Secretele orașului, deblocate."',
                            },
                            {
                                icon: '👨‍✈️',
                                title: 'Pe gustul tău',
                                desc: 'Ghes îți știe gustul. Nu mai ratezi evenimente pentru că nu le-ai găsit la timp. Fără scroll, fără căutări — primești fix ce vrei.',
                            },
                            {
                                icon: '📍',
                                title: 'Aprobat local',
                                desc: 'Dacă apare în Ghes, e real și se potrivește cu profilul tău.',
                            },
                        ].map((feat) => (
                            <div
                                key={feat.title}
                                className="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 flex flex-col gap-4"
                            >
                                <span className="text-4xl">{feat.icon}</span>
                                <h3
                                    className="text-lg font-bold"
                                    style={{ fontFamily: 'Montserrat, sans-serif', color: '#0A1128' }}
                                >
                                    {feat.title}
                                </h3>
                                <p className="text-gray-500 text-sm leading-relaxed">{feat.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── HOW IT WORKS ─────────────────────────────────────────── */}
            <section className="py-24 px-6 bg-white">
                <div className="max-w-4xl mx-auto">
                    <h2
                        className="text-3xl sm:text-4xl font-extrabold text-center mb-16"
                        style={{ fontFamily: 'Montserrat, sans-serif', color: '#0A1128' }}
                    >
                        Cum funcționează?
                    </h2>
                    <div className="flex flex-col md:flex-row items-start gap-6 md:gap-0">

                        {/* Connector 1→2 */}
                        <div className="hidden md:flex items-start justify-center flex-none w-16 pt-5">
                            <div className="w-full h-px" style={{ backgroundColor: '#FF5733', opacity: 0.35 }} />
                        </div>
                        {/* Step 01 */}
                        <div className="flex-1 flex flex-col gap-3">
                            <span className="text-4xl font-extrabold" style={{ fontFamily: 'Montserrat, sans-serif', color: '#FF5733' }}>01</span>
                            <h3 className="text-lg font-bold" style={{ color: '#0A1128', fontFamily: 'Montserrat, sans-serif' }}>Spune-ne gusturile</h3>
                            <p className="text-gray-500 text-sm leading-relaxed"> Discută cu Ghes ca să îți înțeleagă vibe-ul — muzică, teatru, sport, gastronomie sau tot ce-i neobișnuit.</p>
                        </div>

                        {/* Connector 1→2 */}
                        <div className="hidden md:flex items-start justify-center flex-none w-16 pt-5">
                            <div className="w-full h-px" style={{ backgroundColor: '#FF5733', opacity: 0.35 }} />
                        </div>

                        {/* Step 02 */}
                        <div className="flex-1 flex flex-col gap-3">
                            <span className="text-4xl font-extrabold" style={{ fontFamily: 'Montserrat, sans-serif', color: '#FF5733' }}>02</span>
                            <h3 className="text-lg font-bold" style={{ color: '#0A1128', fontFamily: 'Montserrat, sans-serif' }}>Ghes caută 24/7</h3>
                            <p className="text-gray-500 text-sm leading-relaxed">Caută și clasifică evenimentele în timp real din toate colțurile orașului. Tu dormi, noi muncim.</p>
                        </div>

                        {/* Connector 2→3 */}
                        <div className="hidden md:flex items-start justify-center flex-none w-16 pt-5">
                            <div className="w-full h-px" style={{ backgroundColor: '#FF5733', opacity: 0.35 }} />
                        </div>

                        {/* Step 03 */}
                        <div className="flex-1 flex flex-col gap-3">
                            <span className="text-4xl font-extrabold" style={{ fontFamily: 'Montserrat, sans-serif', color: '#FF5733' }}>03</span>
                            <h3 className="text-lg font-bold" style={{ color: '#0A1128', fontFamily: 'Montserrat, sans-serif' }}>Tu alegi diseară</h3>
                            <p className="text-gray-500 text-sm leading-relaxed">Primești un digest personalizat — sau navighezi live prin feed. Un tap și ești pe drum.</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── CTA ──────────────────────────────────────────────────── */}
            <section
                className="py-28 px-6 text-center"
                style={{ backgroundColor: '#0A1128' }}
            >
                <h2
                    className="text-4xl sm:text-5xl font-extrabold mb-4"
                    style={{ fontFamily: 'Montserrat, sans-serif', color: '#F8F9FA' }}
                >
                    Ce faci diseară?
                </h2>
                <p
                    className="text-base mb-10 max-w-md mx-auto"
                    style={{ color: 'rgba(248,249,250,0.6)' }}
                >
                    Lasă Ghes să răspundă. Gratuit, mereu la curent.
                </p>
                <Link
                    href="/register"
                    className="inline-block text-base font-bold px-10 py-4 rounded-full shadow-lg transition-all hover:opacity-90 active:scale-95"
                    style={{ backgroundColor: '#FF5733', color: '#fff' }}
                >
                    Înregistrează-te gratuit
                </Link>
            </section>

            {/* ── FOOTER ───────────────────────────────────────────────── */}
            <footer
                className="py-8 px-6 flex flex-col sm:flex-row items-center justify-between gap-4 border-t"
                style={{ backgroundColor: '#0A1128', borderColor: 'rgba(255,255,255,0.08)' }}
            >
                <img
                    src="/images/logo-dark.png"
                    alt="Ghes"
                    className="h-7 w-auto opacity-80"
                />
                <p className="text-xs" style={{ color: 'rgba(248,249,250,0.35)' }}>
                    © {new Date().getFullYear()} Ghes · Un cadou de la noi pentru tine și orașul tău.
                </p>
            </footer>
        </>
    );
}
