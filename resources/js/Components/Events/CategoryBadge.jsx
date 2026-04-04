import { Badge } from '@/Components/ui/Badge';
import { cn } from '@/lib/utils';

const categoryColors = {
    Music: 'bg-purple-100 text-purple-800 border-purple-200',
    Tech: 'bg-blue-100 text-blue-800 border-blue-200',
    Sports: 'bg-green-100 text-green-800 border-green-200',
    Arts: 'bg-pink-100 text-pink-800 border-pink-200',
    Food: 'bg-orange-100 text-orange-800 border-orange-200',
    Nightlife: 'bg-violet-100 text-violet-800 border-violet-200',
    Business: 'bg-slate-100 text-slate-800 border-slate-200',
    Health: 'bg-teal-100 text-teal-800 border-teal-200',
    Education: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    Community: 'bg-amber-100 text-amber-800 border-amber-200',
    Film: 'bg-rose-100 text-rose-800 border-rose-200',
    Theater: 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200',
    Other: 'bg-gray-100 text-gray-800 border-gray-200',
};

/**
 * @param {Object} props
 * @param {string} props.category
 */
export default function CategoryBadge({ category }) {
    const colorClass = categoryColors[category] || categoryColors.Other;

    return (
        <Badge variant="outline" className={cn(colorClass)}>
            {category}
        </Badge>
    );
}
