<x-layouts.site>
    <div class="max-w-2xl mx-auto px-6 py-12">
        <h1 class="font-serif font-semibold text-4xl">Account settings</h1>
        <p class="text-ink/65 mt-2">Manage your profile, password, and account.</p>

        <div class="mt-8 space-y-5">
            <section class="rounded-2xl border border-ink/8 bg-surface p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </section>

            <section class="rounded-2xl border border-ink/8 bg-surface p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </section>

            <section class="rounded-2xl border border-terra/20 bg-terra/[.03] p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </section>
        </div>
    </div>
</x-layouts.site>
