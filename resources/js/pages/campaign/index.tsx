import AppLayout from '@/layouts/app-layout';
import {Button} from '@/components/ui/button';
import { Head, Link, usePage } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Campaign',
        href: '/campaign/index',
    },
];

export default function Dashboard() {
const { campaigns } = usePage().props as {
  campaigns: {
    data: {
      id: number;
      name: string;
      dialing_type: string;
      product_type: string;
      created_at: string;
      created_by: string;
      assigned_to: string;
      nasabahs_count: number; // Ini relasi count dari backend
    }[];
    links: {
      url: string | null;
      label: string;
      active: boolean;
    }[];
  };
};
    return (
    <AppLayout breadcrumbs={[{ title: 'Campaign', href: '/campaign' }]}>
      <Head title="Campaign List" />

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold text-gray-800">📋 Campaign List</h1>
          <Link
            href="/campaign/upload"
            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition"
          >
            + Upload Campaign
          </Link>
        </div>

        <div className="overflow-auto rounded-lg shadow border border-gray-200">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">#</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Campaign Name</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Product Type</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Created By</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Created At</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {campaigns.data.length > 0 ? (
                campaigns.data.map((item, i) => (
                  <tr key={item.id}>
                    <td className="px-6 py-4 text-sm text-gray-600">{i + 1}</td>
                    <td className="px-6 py-4 text-sm text-gray-800">{item.campaign_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-800">{item.created_by}</td>
                    <td className="px-6 py-4 text-sm text-gray-800">{item.product_type}</td>
                    <td className="px-6 py-4 text-sm text-gray-500">{item.created_at}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={4} className="text-center py-4 text-gray-500">
                    Belum ada campaign.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}