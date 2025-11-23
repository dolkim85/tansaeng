interface SensorRowProps {
  label: string;
  value: number | null | undefined;
  unit: string;
}

export default function SensorRow({ label, value, unit }: SensorRowProps) {
  const hasValue = value !== null && value !== undefined;

  return (
    <div className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
      <dt className="text-sm text-gray-600">{label}</dt>
      <dd className={`text-base font-semibold ${hasValue ? "text-gray-800" : "text-gray-400"}`}>
        {hasValue ? `${value}${unit}` : "측정 대기중"}
      </dd>
    </div>
  );
}
