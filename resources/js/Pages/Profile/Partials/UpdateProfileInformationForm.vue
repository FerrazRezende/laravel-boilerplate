<script setup>
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { __ } from '@/composables/useLang';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

// Photo upload: a separate form/route (profile.photo.store) from the name/email
// one above, submitted the moment a file is picked rather than on this
// section's own submit button, since there isn't one.
const photoForm = useForm({ photo: null });
const photoInput = ref(null);
const previewUrl = ref(null);
const confirmingPhotoRemoval = ref(false);

const initials = computed(() => {
    return (user.name ?? '?')
        .trim()
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const selectPhoto = () => {
    photoInput.value?.click();
};

const onPhotoSelected = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = URL.createObjectURL(file);
    photoForm.photo = file;

    photoForm.post(route('profile.photo.store'), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
                previewUrl.value = null;
            }
            photoInput.value.value = '';
        },
    });
};

const removePhoto = () => {
    photoForm.delete(route('profile.photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => (confirmingPhotoRemoval.value = false),
    });
};
</script>

<template>
    <section>
        <header class="pb-6">
            <h2 class="text-lg font-medium text-foreground">
                {{ __('Profile Photo') }}
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                {{ __('Upload a profile photo to personalize your account.') }}
            </p>
        </header>

        <div class="flex items-center gap-4 border-b border-border pb-6">
            <Avatar class="h-16 w-16">
                <AvatarImage v-if="previewUrl || user.avatar" :src="previewUrl || user.avatar" :alt="user.name" />
                <AvatarFallback class="text-lg font-medium">{{ initials }}</AvatarFallback>
            </Avatar>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    ref="photoInput"
                    type="file"
                    accept="image/jpeg,image/jpg,image/png"
                    class="hidden"
                    :aria-label="__('Click to upload or change your profile photo')"
                    @change="onPhotoSelected"
                />

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="photoForm.processing"
                    @click="selectPhoto"
                >
                    {{ __('Change photo') }}
                </Button>

                <Button
                    v-if="user.avatar"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="confirmingPhotoRemoval = true"
                >
                    {{ __('Remove photo') }}
                </Button>
            </div>

            <p v-if="photoForm.errors.photo" class="text-sm text-destructive">
                {{ photoForm.errors.photo }}
            </p>
        </div>

        <Dialog v-model:open="confirmingPhotoRemoval">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ __('Remove photo') }}</DialogTitle>
                    <DialogDescription>
                        {{ __('Are you sure you want to remove your profile photo?') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirmingPhotoRemoval = false">
                        {{ __('Cancel') }}
                    </Button>
                    <Button variant="destructive" :disabled="photoForm.processing" @click="removePhoto">
                        {{ __('Remove photo') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <header class="pt-6">
            <h2 class="text-lg font-medium text-foreground">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div class="space-y-2">
                <label for="name" class="text-sm font-medium">Name</label>

                <Input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="email" class="text-sm font-medium">Email</label>

                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <p v-if="form.errors.email" class="text-sm text-destructive">
                    {{ form.errors.email }}
                </p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-foreground">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-muted-foreground underline hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-success"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="form.processing">Save</Button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
