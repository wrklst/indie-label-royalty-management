import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/login/code';

export default function LoginCode({ email }: { email: string }) {
    return (
        <>
            <Head title="Sign in with a code" />

            <div className="space-y-6">
                <Form {...store.form()} className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    defaultValue={email}
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="request-login-code-button"
                            >
                                {processing && <Spinner />}
                                Email me a code
                            </Button>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or,</span>
                    <TextLink href={login()}>log in with a password</TextLink>
                </div>
            </div>
        </>
    );
}

LoginCode.layout = {
    title: 'Sign in with a code',
    description:
        'Enter your email address and we will send you a one-time code. No password needed.',
};
