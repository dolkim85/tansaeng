import { useState } from "react";
import type { ReactNode } from "react";

interface CollapsibleSectionProps {
  title: string;
  icon?: string;
  badge?: string | number;
  defaultOpen?: boolean;
  headerColor?: string;
  children: ReactNode;
}

export default function CollapsibleSection({
  title,
  icon,
  badge,
  defaultOpen = false,
  headerColor = "bg-farm-500",
  children,
}: CollapsibleSectionProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <section className="mb-2">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full ${headerColor} px-3 py-2.5 rounded-lg flex items-center justify-between touch-manipulation active:opacity-80`}
      >
        <div className="flex items-center gap-2">
          {icon && <span className="text-lg">{icon}</span>}
          <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
          {badge !== undefined && (
            <span className="text-xs bg-white/30 text-gray-800 px-1.5 py-0.5 rounded-full">
              {badge}
            </span>
          )}
        </div>
        <span
          className={`text-gray-800 transition-transform duration-200 ${
            isOpen ? "rotate-180" : ""
          }`}
        >
          ▼
        </span>
      </button>

      <div
        className={`overflow-hidden transition-all duration-200 ${
          isOpen ? "max-h-[2000px] opacity-100" : "max-h-0 opacity-0"
        }`}
      >
        <div className="bg-white shadow-sm rounded-b-lg p-2 mt-px">
          {children}
        </div>
      </div>
    </section>
  );
}
