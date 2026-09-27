import React, { type ReactNode } from 'react';

/** The card every signed-out screen sits in: Pontiac's name on top, and whose door this is (a consultant's portal). */
export default function AuthCard({ subtitle, children }: { subtitle?: ReactNode; children: ReactNode }) {
    return (
        <div className="auth-page">
            <div className="auth-card">
                <div className="auth-brand">
                    <span className="brand-name">Pontiac</span>
                    {subtitle && <p className="auth-slogan muted small">{subtitle}</p>}
                </div>
                {children}
            </div>
        </div>
    );
}
