import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';

export default function Dashboard({ auth, todos }: PageProps<{todos: [{id: number, title: string, description: string, is_completed: boolean, is_important: boolean, user_id: string}]}>) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">{todos.map(todo => {
                            return <div>
                                <h1>{todo.title}</h1>
                                <h2>{todo.description}</h2>
                                <h3>{todo.is_completed}</h3>
                                <h4>{todo.is_important}</h4>
                                <br/>
                            </div>
                        })}</div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
