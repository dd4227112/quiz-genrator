import { Link, useForm } from '@inertiajs/react';
import Button from '../../Components/Button';
import Checkbox from '../../Components/Checkbox';
import Input from '../../Components/Input';
import GuestLayout from '../../Layouts/GuestLayout';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('login'), {
            onFinish: () => setData('password', ''),
        });
    };

    return (
        <GuestLayout title="Log in" subtitle="Generate interview questions for any role, course, or program.">
            <form onSubmit={submit} className="space-y-5" noValidate>
                <Input
                    label="Email"
                    type="email"
                    name="email"
                    autoComplete="email"
                    autoFocus
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Input
                    label="Password"
                    type="password"
                    name="password"
                    autoComplete="current-password"
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />
                <Checkbox label="Remember me" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                <Button type="submit" className="w-full" loading={processing}>
                    Log in
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-600">
                No account yet?{' '}
                <Link href={route('register')} className="font-semibold text-indigo-600 hover:text-indigo-500">
                    Create one
                </Link>
            </p>
        </GuestLayout>
    );
}
