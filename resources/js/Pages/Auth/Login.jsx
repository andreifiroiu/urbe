import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/Card';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Intră în cont — Ghes" />
            <div
                className="min-h-screen flex flex-col items-center justify-center px-4"
                style={{ backgroundColor: '#0A1128' }}
            >
                <Link href="/" className="mb-8">
                    <img
                        src="/images/logo-dark.png"
                        alt="Ghes"
                        className="h-14 w-auto"
                    />
                </Link>
                <Card className="w-full max-w-md border-0 shadow-2xl">
                    <CardHeader className="text-center">
                        <CardTitle className="text-2xl" style={{ fontFamily: 'Montserrat, sans-serif' }}>
                            Bine ai revenit
                        </CardTitle>
                        <CardDescription>
                            Intră în contul tău Ghes
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit}>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="tu@exemplu.com"
                                    autoComplete="email"
                                    required
                                />
                                {errors.email && (
                                    <p className="text-sm text-red-600">{errors.email}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="password">Parolă</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Introdu parola"
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <p className="text-sm text-red-600">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                        <CardFooter className="flex flex-col gap-4">
                            <Button
                                type="submit"
                                className="w-full font-semibold"
                                style={{ backgroundColor: '#FF5733', color: '#fff' }}
                                disabled={processing}
                            >
                                {processing ? 'Se conectează...' : 'Intră în cont'}
                            </Button>
                            <p className="text-sm text-gray-500 text-center">
                                Nu ai cont?{' '}
                                <Link
                                    href="/register"
                                    className="font-medium hover:underline"
                                    style={{ color: '#FF5733' }}
                                >
                                    Înregistrează-te
                                </Link>
                            </p>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
