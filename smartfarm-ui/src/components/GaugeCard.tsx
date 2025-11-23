interface GaugeCardProps {
  icon: string;
  title: string;
  value: number | null | undefined;
  unit: string;
  maxValue: number;
  color?: "green" | "blue" | "orange";
}

export default function GaugeCard({
  icon,
  title,
  value,
  unit,
  maxValue,
  color = "green",
}: GaugeCardProps) {
  const hasValue = value !== null && value !== undefined;
  const percentage = hasValue ? Math.min((value / maxValue) * 100, 100) : 0;

  const colorClasses = {
    green: "bg-green-600",
    blue: "bg-blue-600",
    orange: "bg-orange-500",
  };

  return (
    <div className="bg-white rounded-xl shadow-md p-6 space-y-4">
      {/* 헤더 */}
      <div className="flex items-center gap-3">
        <span className="text-3xl">{icon}</span>
        <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
      </div>

      {/* 값 표시 */}
      <div className="text-center">
        <div className={`text-5xl font-bold ${hasValue ? "text-green-600" : "text-gray-300"}`}>
          {hasValue ? value : "-"}
          {hasValue && <span className="text-2xl font-normal ml-2">{unit}</span>}
        </div>
        {!hasValue && (
          <div className="text-sm text-gray-500 mt-2">측정 대기중</div>
        )}
      </div>

      {/* 게이지 바 */}
      <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
        <div
          className={`h-full transition-all duration-500 ${colorClasses[color]}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}
