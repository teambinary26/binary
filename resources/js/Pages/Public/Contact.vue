<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const page = usePage();
const gov = computed(() => page.props.gov);

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const submit = () => form.post(route('site.contact.submit'));
</script>

<template>
    <PublicLayout>
        <Head title="Contact" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <h1>Contact the MSWDO</h1>
            </div>
        </div>
        <div class="mx-auto grid max-w-7xl gap-4 px-4 py-6 md:py-8 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-h">Office information</div>
                <div class="panel-body space-y-2 text-sm">
                    <p><strong>{{ gov.agency }}</strong></p>
                    <p>{{ gov.address }}</p>
                    <p>Telephone: {{ gov.phone }}</p>
                    <p>Email: {{ gov.email }}</p>
                    <p>Hours: {{ gov.office_hours }}</p>
                    <p class="mt-4 border-t border-gov-border pt-3 text-gov-muted">Walk-in applicants are served at the MSWDO windows inside the Municipal Hall. Online applications may be filed at any time.</p>
                </div>
            </div>
            <div class="panel">
                <div class="panel-h">Send a message</div>
                <form class="panel-body space-y-3" @submit.prevent="submit">
                    <div>
                        <label for="name">Full name</label>
                        <input id="name" v-model="form.name" required>
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" v-model="form.email" type="email" required>
                    </div>
                    <div>
                        <label for="subject">Subject</label>
                        <input id="subject" v-model="form.subject" required>
                    </div>
                    <div>
                        <label for="message">Message</label>
                        <textarea id="message" v-model="form.message" rows="5" required />
                    </div>
                    <button class="btn-primary w-full sm:w-auto" type="submit" :disabled="form.processing">Submit</button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
