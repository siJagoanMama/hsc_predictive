import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Play, Pause, Square, RotateCcw } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Campaign {
    id: number;
    campaign_name: string;
    status: 'pending' | 'running' | 'paused' | 'completed' | 'stopped';
    is_active: boolean;
}

interface CampaignControlsProps {
    campaign: Campaign;
    onStatusChange?: (campaign: Campaign) => void;
}

export function CampaignControls({ campaign, onStatusChange }: CampaignControlsProps) {
    const [isLoading, setIsLoading] = useState(false);

    const handleAction = async (action: string) => {
        setIsLoading(true);
        
        try {
            const response = await fetch(`/api/campaigns/${campaign.id}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                const data = await response.json();
                onStatusChange?.(data.campaign);
                router.reload({ only: ['campaigns'] });
            } else {
                const error = await response.json();
                alert(error.message || 'Action failed');
            }
        } catch (error) {
            console.error('Action failed:', error);
            alert('Action failed');
        } finally {
            setIsLoading(false);
        }
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'secondary',
            running: 'default',
            paused: 'outline',
            completed: 'secondary',
            stopped: 'destructive',
        } as const;

        return (
            <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>
                {status.toUpperCase()}
            </Badge>
        );
    };

    return (
        <div className="flex items-center gap-2">
            {getStatusBadge(campaign.status)}
            
            <div className="flex gap-1">
                {campaign.status === 'pending' || campaign.status === 'stopped' ? (
                    <Button
                        size="sm"
                        onClick={() => handleAction('start')}
                        disabled={isLoading}
                        className="h-8 w-8 p-0"
                    >
                        <Play className="h-4 w-4" />
                    </Button>
                ) : null}

                {campaign.status === 'running' ? (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleAction('pause')}
                            disabled={isLoading}
                            className="h-8 w-8 p-0"
                        >
                            <Pause className="h-4 w-4" />
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleAction('stop')}
                            disabled={isLoading}
                            className="h-8 w-8 p-0"
                        >
                            <Square className="h-4 w-4" />
                        </Button>
                    </>
                ) : null}

                {campaign.status === 'paused' ? (
                    <>
                        <Button
                            size="sm"
                            onClick={() => handleAction('resume')}
                            disabled={isLoading}
                            className="h-8 w-8 p-0"
                        >
                            <Play className="h-4 w-4" />
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleAction('stop')}
                            disabled={isLoading}
                            className="h-8 w-8 p-0"
                        >
                            <Square className="h-4 w-4" />
                        </Button>
                    </>
                ) : null}
            </div>
        </div>
    );
}