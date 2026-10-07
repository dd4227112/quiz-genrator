import { Link, useForm } from '@inertiajs/react';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import GuestLayout from '../../Layouts/GuestLayout';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })),
        });
    };

    return (
        <GuestLayout title="Create your account" subtitle="Start generating interview questions in seconds.">
            <form onSubmit={submit} className="space-y-5" noValidate>
                <Input
                    label="Name"
                    name="name"
                    autoComplete="name"
                    autoFocus
                    required
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />
                <Input
                    label="Email"
                    type="email"
                    name="email"
                    autoComplete="email"
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Input
                    label="Password"
                    type="password"
                    name="password"
                    autoComplete="new-password"
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />
                <Input
                    label="Confirm password"
                    type="password"
                    name="password_confirmation"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                />
                <Button type="submit" className="w-full" loading={processing}>
                    Create account
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-600">
                Already registered?{' '}
                <Link href={route('login')} className="font-semibold text-indigo-600 hover:text-indigo-500">
                    Log in
                </Link>
            </p>
        </GuestLayout>
    );
}
