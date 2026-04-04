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

  const colorMap = {
    green: "#059669",
    blue: "#2563eb",
    orange: "#f97316",
  };

  return (
    <div className="bg-white rounded-xl shadow-card hover:shadow-card-hover transition-all duration-200 p-6">
      {/* 헤더 */}
      <div className="flex items-center gap-3 mb-4">
        <span className="text-3xl">{icon}</span>
        <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
      </div>

      {/* 값 표시 */}
      <div className="text-center mb-4">
        <div className={`text-5xl font-bold ${hasValue ? 'text-farm-600' : 'text-gray-300'}`}>
          {hasValue ? value : "-"}
          {hasValue && (
            <span className="text-2xl font-normal ml-2">{unit}</span>
          )}
        </div>
        {!hasValue && (
          <div className="text-sm text-gray-500 mt-2">측정 대기중</div>
        )}
      </div>

      {/* 게이지 바 */}
      <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
        <div
          className="h-full transition-all duration-500"
          style={{
            background: colorMap[color],
            width: `${percentage}%`
          }}
        />
      </div>
    </div>
  );
}
