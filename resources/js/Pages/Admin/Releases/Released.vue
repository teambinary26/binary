<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    releases: { type: Object, required: true },
});
</script>

<template>
    <AdminLayout>
        <Head title="Released Assistance" />
        <PageHeader title="Released Assistance" kicker="Disbursement register" />
        <table class="data-table">
            <thead><tr><th>Reference no.</th><th>Application</th><th>Applicant</th><th>Program</th><th>Amount</th><th>Released</th><th>Officer</th><th>Verification code</th></tr></thead>
            <tbody>
                <tr v-for="row in releases.data" :key="row.id">
                    <td data-label="Ref">{{ row.reference_no }}</td>
                    <td data-label="App">{{ row.application?.application_no }}</td>
                    <td data-label="Applicant">{{ row.application?.applicant?.full_name }}</td>
                    <td data-label="Program">{{ row.application?.program?.name }}</td>
                    <td data-label="Amount">{{ row.amount_formatted }}</td>
                    <td data-label="Date">{{ row.released_at }}</td>
                    <td data-label="Officer">{{ row.officer }}</td>
                    <td data-label="Code">{{ row.verification_code }}</td>
                </tr>
            </tbody>
        </table>
        <Pagination :links="releases.links" />
    </AdminLayout>
</template>
