package com.itsme.app.ui.traveldates
import android.view.*
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.*
import com.itsme.app.R
import com.itsme.app.data.entities.TravelDate
import com.itsme.app.databinding.ItemTravelDateBinding

class TravelDateAdapter(val onActivate:(TravelDate)->Unit, val onComplete:(TravelDate)->Unit, val onDelete:(TravelDate)->Unit) :
    ListAdapter<TravelDate, TravelDateAdapter.VH>(object: DiffUtil.ItemCallback<TravelDate>() {
        override fun areItemsTheSame(a: TravelDate, b: TravelDate) = a.id==b.id
        override fun areContentsTheSame(a: TravelDate, b: TravelDate) = a==b
    }) {
    inner class VH(val b: ItemTravelDateBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemTravelDateBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val td = getItem(pos); val ctx = h.b.root.context
        h.b.tvTravelName.text = td.name
        h.b.tvDates.text = if (td.startDate.isNotEmpty()) "${td.startDate}  →  ${td.endDate}" else ""
        when {
            td.isActive    -> { h.b.tvStatus.text = "● ACTIVO";     h.b.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.colorActive)) }
            td.isCompleted -> { h.b.tvStatus.text = "✓ COMPLETADO"; h.b.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.colorPrimary)) }
            else           -> { h.b.tvStatus.text = "○ Pendiente";  h.b.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.colorPending)) }
        }
        h.b.btnActivate.isEnabled = !td.isActive && !td.isCompleted
        h.b.btnComplete.isEnabled = td.isActive
        h.b.btnActivate.setOnClickListener { onActivate(td) }
        h.b.btnComplete.setOnClickListener { onComplete(td) }
        h.b.btnDelete.setOnClickListener { onDelete(td) }
    }
}
