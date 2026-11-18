import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { authenticate, create, store } from '@/routes/login/code';

type Props = {
    email: string;
    status?: string;
    minutesValid: number;
};

export default function LoginCodeVerify({
    email,
    status,
    minutesValid,
}: Props) {
    return (
        <>
            <Head title="Enter your code" />

            <div className="space-y-6">
                <p
                    className="text-center text-sm text-muted-foreground"
                    role="status"
                >
                    {status ??
                        'If an account exists for this address, we sent a code.'}{' '}
                    Look for it in the inbox of{' '}
                    <span className="font-medium text-foreground">{email}</span>
                    . It is valid for {minutesValid} minutes.
                </p>

                <Form
                    {...authenticate.form()}
                    resetOnError={['code']}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="code">Sign-in code</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    required
                                    autoFocus
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={12}
                                    placeholder="6-digit code"
                                    className="text-center text-lg tracking-[0.3em] placeholder:tracking-normal"
                                />
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="verify-login-code-button"
                            >
                                {processing && <Spinner />}
                                Sign in
                            </Button>
                        </>
                    )}
                </Form>

                <Form {...store.form()} className="text-center">
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="email" value={email} />
                            <Button
                                type="submit"
                                variant="link"
                                className="h-auto p-0 text-sm"
                                disabled={processing}
                            >
                                Send a new code
                            </Button>
                            <InputError message={errors.email} />
                        </>
                    )}
                </Form>

                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href={create()}>
                        Use a different email address
                    </TextLink>
                </div>
            </div>
        </>
    );
}

LoginCodeVerify.layout = {
    title: 'Enter your code',
    description: 'Type the 6-digit code from the email we sent you.',
};
